package com.freefcc.app

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.LocationManager
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Build
import androidx.core.content.ContextCompat
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.util.Locale
import java.util.concurrent.CopyOnWriteArrayList
import java.util.concurrent.atomic.AtomicInteger

object TelemetryCollector {

    private val pendingFeatureEvents = CopyOnWriteArrayList<FeatureEvent>()
    private val disconnectionCount = AtomicInteger(0)
    private val crcErrorCount = AtomicInteger(0)

    private var fccStartTime: Long = 0L
    private var keepaliveCount = AtomicInteger(0)
    private var ceResetBlocks = AtomicInteger(0)

    fun trackFeature(feature: String, success: Boolean, metadata: Map<String, Any?>? = null) {
        pendingFeatureEvents.add(FeatureEvent(feature, success, metadata))
    }

    fun trackTab(tabName: String) {
        trackFeature("tab_switch", true, mapOf("tab" to tabName))
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
        val location = readLastKnownLocation(context)

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

        withContext(Dispatchers.IO) {
            TelemetryApi.sendFccSession(
                token = token,
                action = action,
                success = success,
                durationSeconds = if (action == "keepalive_stop" || action == "fcc_disable") getFccDurationSeconds() else null,
                keepaliveCount = if (action == "keepalive_stop") getKeepaliveCount() else null,
                ceResetBlocks = if (action == "keepalive_stop") getCeResetBlocks() else null,
                aircraftSerial = aircraftSerial,
                controllerModel = controllerModel,
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

    private fun readLastKnownLocation(context: Context): Pair<Double, Double>? {
        val granted = ContextCompat.checkSelfPermission(
            context, Manifest.permission.ACCESS_COARSE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED
        if (!granted) return null

        return try {
            val lm = context.getSystemService(Context.LOCATION_SERVICE) as LocationManager
            val providers = listOf(
                LocationManager.NETWORK_PROVIDER,
                LocationManager.GPS_PROVIDER,
                LocationManager.PASSIVE_PROVIDER
            )
            for (provider in providers) {
                if (!lm.isProviderEnabled(provider)) continue
                val loc = lm.getLastKnownLocation(provider) ?: continue
                return Pair(loc.latitude, loc.longitude)
            }
            null
        } catch (_: Exception) {
            null
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
