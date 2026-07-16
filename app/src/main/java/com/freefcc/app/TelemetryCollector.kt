package com.freefcc.app

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Geocoder
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Build
import android.os.Bundle
import android.os.HandlerThread
import android.os.Looper
import android.os.SystemClock
import androidx.core.content.ContextCompat
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withContext
import kotlinx.coroutines.withTimeoutOrNull
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import java.util.concurrent.CopyOnWriteArrayList
import java.util.concurrent.atomic.AtomicInteger
import kotlin.coroutines.resume

object TelemetryCollector {

    private const val FRESH_LOCATION_MAX_AGE_MS = 30 * 60 * 1000L
    private const val STALE_LOCATION_MAX_AGE_MS = 6 * 60 * 60 * 1000L
    private const val LOCATION_FIX_TIMEOUT_MS = 20_000L
    private const val ACTIVITY_FLUSH_THRESHOLD = 15

    private val pendingFeatureEvents = CopyOnWriteArrayList<FeatureEvent>()
    private val pendingActivityEvents = CopyOnWriteArrayList<ActivityEvent>()
    private val disconnectionCount = AtomicInteger(0)
    private val crcErrorCount = AtomicInteger(0)

    private val isoFmt = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", Locale.US).apply {
        timeZone = TimeZone.getTimeZone("UTC")
    }

    private var fccStartTime: Long = 0L
    private var keepaliveCount = AtomicInteger(0)
    private var ceResetBlocks = AtomicInteger(0)

    @Volatile
    private var cachedCoords: Pair<Double, Double>? = null
    @Volatile
    private var cachedCoordsAtMs: Long = 0L

    data class LocationInfo(
        val latitude: Double?,
        val longitude: Double?,
        val province: String?,
        val district: String?,
        val neighborhood: String?
    )

    fun trackFeature(feature: String, success: Boolean, metadata: Map<String, Any?>? = null) {
        pendingFeatureEvents.add(FeatureEvent(feature, success, metadata))
    }

    fun trackTab(tabName: String) {
        trackFeature("tab_switch", true, mapOf("tab" to tabName))
    }

    /** Queues a UI activity log line for upload (same messages shown in the app log panel). */
    fun trackActivity(message: String, level: String? = null) {
        val resolvedLevel = level ?: inferActivityLevel(message)
        val loggedAt = synchronized(isoFmt) { isoFmt.format(Date()) }
        pendingActivityEvents.add(
            ActivityEvent(
                level = resolvedLevel,
                message = message,
                loggedAt = loggedAt,
                appVersion = FccViewModel.APP_VERSION
            )
        )
    }

    private fun inferActivityLevel(message: String): String {
        val lower = message.lowercase(Locale("tr", "TR"))
        return when {
            lower.contains("başarısız") ||
                lower.contains("alınamadı") ||
                lower.contains("algılanmadı") ||
                lower.contains("hata") ||
                lower.contains("crash") ||
                lower.contains("failed") ||
                lower.contains("error") -> "error"
            lower.contains("uyarı") ||
                lower.contains("warn") ||
                lower.contains("timeout") ||
                lower.contains("yeniden") -> "warn"
            else -> "info"
        }
    }

    fun incrementDisconnections() {
        disconnectionCount.incrementAndGet()
    }

    fun incrementCrcErrors() {
        crcErrorCount.incrementAndGet()
    }

    fun markFccStart() {
        fccStartTime = System.currentTimeMillis()
        keepaliveCount.set(0)
        ceResetBlocks.set(0)
    }

    fun incrementKeepaliveCount() {
        keepaliveCount.incrementAndGet()
    }

    /** Each successful keepalive re-apply is treated as blocking a potential CE reset. */
    fun incrementCeResetBlocks() {
        ceResetBlocks.incrementAndGet()
    }

    fun getFccDurationSeconds(): Int {
        if (fccStartTime == 0L) return 0
        return ((System.currentTimeMillis() - fccStartTime) / 1000).toInt()
    }

    fun getKeepaliveCount(): Int = keepaliveCount.get()

    fun getCeResetBlocks(): Int = ceResetBlocks.get()

    fun getAndResetDisconnections(): Int = disconnectionCount.getAndSet(0)

    fun getAndResetCrcErrors(): Int = crcErrorCount.getAndSet(0)

    suspend fun sendDeviceTelemetry(
        context: Context,
        firmwareVersion: String? = null,
        hardwareVersion: String? = null,
        bootloaderVersion: String? = null,
        aircraftSerial: String? = null,
        detectedPort: Int? = null
    ) {
        val token = AuthManager.getToken(context) ?: return
        val controllerModel = try { Build.DEVICE } catch (_: Exception) { null }
        val androidVersion = try { Build.VERSION.RELEASE } catch (_: Exception) { null }
        val droneModel = aircraftSerial?.let { inferDroneModel(it) }
        val networkType = detectNetworkType(context)
        val locale = Locale.getDefault()
        val countryCode = locale.country.takeIf { it.isNotEmpty() }
        val localeTag = locale.toLanguageTag()
        val location = obtainCoordinates(context)

        withContext(Dispatchers.IO) {
            val pingMs = TelemetryApi.measureServerPing(token)
            TelemetryApi.sendDeviceTelemetry(
                token = token,
                controllerModel = controllerModel,
                androidVersion = androidVersion,
                firmwareVersion = firmwareVersion,
                hardwareVersion = hardwareVersion,
                bootloaderVersion = bootloaderVersion,
                aircraftSerial = aircraftSerial,
                droneModel = droneModel,
                detectedPort = detectedPort,
                appVersion = FccViewModel.APP_VERSION,
                networkType = networkType,
                countryCode = countryCode,
                locale = localeTag,
                latitude = location?.first,
                longitude = location?.second,
                serverPingMs = pingMs
            )
        }
    }

    suspend fun sendFccSession(
        context: Context,
        action: String,
        success: Boolean,
        failureReason: String? = null,
        aircraftSerial: String? = null
    ) {
        val token = AuthManager.getToken(context) ?: return
        val controllerModel = try { Build.DEVICE } catch (_: Exception) { null }
        // device_model = bağlı drone; RC kumanda modeli (Build.MODEL) değil
        val serial = aircraftSerial?.takeIf { it.isNotBlank() }
        val deviceModel = serial?.let { inferDroneModel(it) }
        val location = resolveLocationInfo(context)

        withContext(Dispatchers.IO) {
            TelemetryApi.sendFccSession(
                token = token,
                action = action,
                success = success,
                durationSeconds = if (action == "keepalive_stop" || action == "fcc_disable") getFccDurationSeconds() else null,
                keepaliveCount = if (action == "keepalive_stop") getKeepaliveCount() else null,
                ceResetBlocks = if (action == "keepalive_stop") getCeResetBlocks() else null,
                aircraftSerial = serial,
                controllerModel = controllerModel,
                deviceModel = deviceModel,
                latitude = location.latitude,
                longitude = location.longitude,
                province = location.province,
                district = location.district,
                neighborhood = location.neighborhood,
                failureReason = failureReason
            )
        }
    }

    suspend fun sendConnectionMetrics(
        context: Context,
        connectTimeMs: Int? = null,
        commandLatencyMs: Int? = null,
        portUsed: Int? = null
    ) {
        val token = AuthManager.getToken(context) ?: return
        val controllerModel = try { Build.DEVICE } catch (_: Exception) { null }

        withContext(Dispatchers.IO) {
            TelemetryApi.sendConnectionMetrics(
                token = token,
                connectTimeMs = connectTimeMs,
                commandLatencyMs = commandLatencyMs,
                disconnectionCount = getAndResetDisconnections(),
                crcErrorCount = getAndResetCrcErrors(),
                portUsed = portUsed,
                controllerModel = controllerModel
            )
        }
    }

    suspend fun sendErrorLog(
        context: Context,
        errorType: String,
        message: String,
        stackTrace: String? = null,
        errorContext: String? = null
    ) {
        val token = AuthManager.getToken(context) ?: return
        val controllerModel = try { Build.DEVICE } catch (_: Exception) { null }

        withContext(Dispatchers.IO) {
            TelemetryApi.sendErrorLog(
                token = token,
                errorType = errorType,
                message = message,
                stackTrace = stackTrace,
                context = errorContext,
                appVersion = FccViewModel.APP_VERSION,
                controllerModel = controllerModel
            )
        }
    }

    suspend fun flushFeatureEvents(context: Context) {
        if (pendingFeatureEvents.isEmpty()) return
        val token = AuthManager.getToken(context) ?: return
        val batch = ArrayList(pendingFeatureEvents)
        pendingFeatureEvents.clear()

        withContext(Dispatchers.IO) {
            val ok = TelemetryApi.sendFeatureUsageBatch(token, batch)
            if (!ok) {
                pendingFeatureEvents.addAll(0, batch)
            }
        }
    }

    suspend fun flushActivityLogs(context: Context, force: Boolean = false) {
        if (pendingActivityEvents.isEmpty()) return
        if (!force && pendingActivityEvents.size < ACTIVITY_FLUSH_THRESHOLD) return

        val token = AuthManager.getToken(context) ?: return
        val batch = ArrayList(pendingActivityEvents)
        pendingActivityEvents.clear()

        withContext(Dispatchers.IO) {
            val ok = TelemetryApi.sendActivityLogBatch(token, batch)
            if (!ok) {
                pendingActivityEvents.addAll(0, batch)
            }
        }
    }

    /** Flushes both feature and activity queues (periodic / onStop). */
    suspend fun flushPendingTelemetry(context: Context) {
        flushFeatureEvents(context)
        flushActivityLogs(context, force = true)
    }

    private fun detectNetworkType(context: Context): String {
        return try {
            val cm = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
            val network = cm.activeNetwork ?: return "none"
            val caps = cm.getNetworkCapabilities(network) ?: return "none"
            when {
                caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) -> "wifi"
                caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) -> "cellular"
                caps.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) -> "ethernet"
                caps.hasTransport(NetworkCapabilities.TRANSPORT_BLUETOOTH) -> "bluetooth"
                else -> "other"
            }
        } catch (_: Exception) {
            "unknown"
        }
    }

    private suspend fun resolveLocationInfo(context: Context): LocationInfo {
        val coords = obtainCoordinates(context)
        if (coords == null) {
            return LocationInfo(null, null, null, null, null)
        }

        val address = withContext(Dispatchers.IO) {
            reverseGeocode(context, coords.first, coords.second)
        }
        return LocationInfo(
            latitude = coords.first,
            longitude = coords.second,
            province = address?.province,
            district = address?.district,
            neighborhood = address?.neighborhood
        )
    }

    private data class AddressParts(
        val province: String?,
        val district: String?,
        val neighborhood: String?
    )

    @Suppress("DEPRECATION")
    private fun reverseGeocode(context: Context, lat: Double, lng: Double): AddressParts? {
        return try {
            if (!Geocoder.isPresent()) return null
            val geocoder = Geocoder(context, Locale("tr", "TR"))
            val results = geocoder.getFromLocation(lat, lng, 1)
            val address = results?.firstOrNull() ?: return null

            val province = address.adminArea?.trim()?.ifEmpty { null }
            val district = listOfNotNull(address.subAdminArea, address.locality)
                .map { it.trim() }
                .firstOrNull { it.isNotEmpty() }
            val neighborhood = listOfNotNull(
                address.subLocality,
                address.thoroughfare,
                address.featureName
            )
                .map { it.trim() }
                .firstOrNull { it.isNotEmpty() && it != district && it != province }

            AddressParts(province, district, neighborhood)
        } catch (_: Exception) {
            null
        }
    }

    private fun hasLocationPermission(context: Context): Boolean {
        val fine = ContextCompat.checkSelfPermission(
            context, Manifest.permission.ACCESS_FINE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED
        val coarse = ContextCompat.checkSelfPermission(
            context, Manifest.permission.ACCESS_COARSE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED
        return fine || coarse
    }

    private fun hasFineLocationPermission(context: Context): Boolean {
        return ContextCompat.checkSelfPermission(
            context, Manifest.permission.ACCESS_FINE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED
    }

    private fun locationAgeMs(location: Location): Long {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.JELLY_BEAN_MR1) {
            val ageNs = SystemClock.elapsedRealtimeNanos() - location.elapsedRealtimeNanos
            (ageNs / 1_000_000L).coerceAtLeast(0L)
        } else {
            (System.currentTimeMillis() - location.time).coerceAtLeast(0L)
        }
    }

    private fun readBestLastKnownLocation(context: Context): Location? {
        if (!hasLocationPermission(context)) return null

        return try {
            val lm = context.getSystemService(Context.LOCATION_SERVICE) as LocationManager
            val providers = buildList {
                if (hasFineLocationPermission(context)) {
                    add(LocationManager.GPS_PROVIDER)
                }
                add(LocationManager.NETWORK_PROVIDER)
                add(LocationManager.PASSIVE_PROVIDER)
            }

            var best: Location? = null
            for (provider in providers) {
                if (!lm.isProviderEnabled(provider)) continue
                val loc = lm.getLastKnownLocation(provider) ?: continue
                val current = best
                if (current == null ||
                    locationAgeMs(loc) < locationAgeMs(current) ||
                    (locationAgeMs(loc) == locationAgeMs(current) && loc.accuracy < current.accuracy)
                ) {
                    best = loc
                }
            }
            best
        } catch (_: Exception) {
            null
        }
    }

    /** Call early (permission grant / onResume) so flight start has a warm GPS cache. */
    fun prefetchLocation(context: Context) {
        if (!hasLocationPermission(context)) return
        Thread {
            try {
                kotlinx.coroutines.runBlocking {
                    obtainCoordinates(context.applicationContext)
                }
            } catch (_: Exception) {
            }
        }.start()
    }

    private fun rememberCoords(coords: Pair<Double, Double>): Pair<Double, Double> {
        cachedCoords = coords
        cachedCoordsAtMs = SystemClock.elapsedRealtime()
        return coords
    }

    private fun readCachedCoords(): Pair<Double, Double>? {
        val coords = cachedCoords ?: return null
        val age = SystemClock.elapsedRealtime() - cachedCoordsAtMs
        return if (age in 0 until FRESH_LOCATION_MAX_AGE_MS) coords else null
    }

    private suspend fun obtainCoordinates(context: Context): Pair<Double, Double>? {
        if (!hasLocationPermission(context)) return null

        readCachedCoords()?.let { return it }

        val last = readBestLastKnownLocation(context)
        if (last != null && locationAgeMs(last) <= FRESH_LOCATION_MAX_AGE_MS) {
            return rememberCoords(Pair(last.latitude, last.longitude))
        }

        val fresh = requestFreshLocation(context)
        if (fresh != null) {
            return rememberCoords(fresh)
        }

        if (last != null && locationAgeMs(last) <= STALE_LOCATION_MAX_AGE_MS) {
            return rememberCoords(Pair(last.latitude, last.longitude))
        }

        return readCachedCoords()
            ?: cachedCoords?.takeIf {
                SystemClock.elapsedRealtime() - cachedCoordsAtMs <= STALE_LOCATION_MAX_AGE_MS
            }
    }

    private suspend fun requestFreshLocation(context: Context): Pair<Double, Double>? {
        val lm = try {
            context.getSystemService(Context.LOCATION_SERVICE) as LocationManager
        } catch (_: Exception) {
            return null
        }

        val providers = buildList {
            if (hasFineLocationPermission(context) && lm.isProviderEnabled(LocationManager.GPS_PROVIDER)) {
                add(LocationManager.GPS_PROVIDER)
            }
            if (lm.isProviderEnabled(LocationManager.NETWORK_PROVIDER)) {
                add(LocationManager.NETWORK_PROVIDER)
            }
            if (lm.isProviderEnabled(LocationManager.PASSIVE_PROVIDER)) {
                add(LocationManager.PASSIVE_PROVIDER)
            }
        }
        if (providers.isEmpty()) return null

        return withTimeoutOrNull(LOCATION_FIX_TIMEOUT_MS) {
            suspendCancellableCoroutine { cont ->
                val handlerThread = HandlerThread("fcc-location").also { it.start() }
                val looper: Looper = handlerThread.looper
                var completed = false
                lateinit var listener: LocationListener

                fun finish(result: Pair<Double, Double>?) {
                    if (completed) return
                    completed = true
                    try {
                        lm.removeUpdates(listener)
                    } catch (_: Exception) {
                    }
                    handlerThread.quitSafely()
                    if (cont.isActive) {
                        cont.resume(result)
                    }
                }

                listener = object : LocationListener {
                    override fun onLocationChanged(location: Location) {
                        finish(Pair(location.latitude, location.longitude))
                    }

                    override fun onProviderEnabled(provider: String) {}
                    override fun onProviderDisabled(provider: String) {}

                    @Deprecated("Deprecated in Java")
                    override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) {}
                }

                cont.invokeOnCancellation {
                    if (completed) return@invokeOnCancellation
                    completed = true
                    try {
                        lm.removeUpdates(listener)
                    } catch (_: Exception) {
                    }
                    handlerThread.quitSafely()
                }

                try {
                    for (provider in providers) {
                        lm.requestLocationUpdates(provider, 0L, 0f, listener, looper)
                    }
                } catch (_: SecurityException) {
                    finish(null)
                } catch (_: Exception) {
                    finish(null)
                }
            }
        }
    }

    private fun inferDroneModel(serial: String): String? {
        if (serial.length < 7) return null
        return when {
            serial.startsWith("1581F") -> "DJI Mini Series"
            serial.startsWith("1581U") -> "DJI Mavic Series"
            serial.startsWith("1581W") -> "DJI Air Series"
            serial.startsWith("WA") -> "DJI Avata"
            serial.startsWith("WM") -> "DJI Mavic"
            else -> null
        }
    }
}
