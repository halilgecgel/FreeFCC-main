package com.freefcc.app

import android.app.Application
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Immutable UI state for the entire app.
 *
 * The ViewModel updates this via copy() and the Compose layer observes it
 * with collectAsStateWithLifecycle(). Every field here represents something
 * the UI needs to render.
 */
data class AppState(
    val status: String = "idle",
    val message: String = "",
    val isConnected: Boolean = false,
    val isFccEnabled: Boolean = false,
    val is4gBusy: Boolean = false,
    val fourGMessage: String = "",
    val isBusy: Boolean = false,
    val isHardwareBusy: Boolean = false,
    val busyProgress: Float = 0f,
    val aircraftSerial: String = "",
    val controllerModel: String = "",
    val deviceInfo: String = "",
    val isQueryingInfo: Boolean = false,
    val autoFcc: Boolean = false,
    val isLedBusy: Boolean = false,
    val ledStatus: String = "",
    val logMessages: List<String> = emptyList(),
    // Update state
    val updateInfo: UpdateInfo? = null,
    val isCheckingUpdate: Boolean = false,
    val isDownloadingUpdate: Boolean = false,
    val updateDownloadProgress: Float = 0f,
    val isUpdateDownloaded: Boolean = false,
    val needsInstallPermission: Boolean = false,
    val updateAvailable: Boolean = false,
    val updateChecked: Boolean = false,
    val updateCheckFailed: Boolean = false,
    val isUpdateForced: Boolean = false,
    // Notification state
    val pendingNotifications: List<AppNotification> = emptyList(),
    val showNotificationDialog: Boolean = false,
    val currentNotification: AppNotification? = null,
    // Keepalive state
    val isKeepaliveRunning: Boolean = false
)

/**
 * Manages all app state and business logic.
 *
 * The UI never touches the transport layer directly. It calls methods on
 * this ViewModel, which runs operations on a background thread (Dispatchers.IO)
 * and updates the observable [state] flow. The UI reacts to state changes
 * automatically via Compose's collectAsStateWithLifecycle().
 *
 * @param app The Application context, used for SharedPreferences and asset loading
 */
class FccViewModel(private val app: Application) : AndroidViewModel(app) {

    private val _state = MutableStateFlow(AppState())
    val state: StateFlow<AppState> = _state.asStateFlow()

    private val transport = DumlTransport()
    private val prefs = app.getSharedPreferences("freefcc", Context.MODE_PRIVATE)

    init {
        // MainActivity.onCreate() calls init() below on every Activity re-creation
        // (e.g. config change), but this class init{} runs exactly once per
        // ViewModel instance — the collector must live here, not in init().
        viewModelScope.launch {
            HardwareLock.busy.collect { busy -> update { copy(isHardwareBusy = busy) } }
        }
    }

    /** Claims the shared hardware lock for one operation. Returns false if another (including the keepalive service) is already running. */
    private fun beginHardwareOp(): Boolean = HardwareLock.tryBegin()

    /** Releases the shared hardware lock. Must run in a finally block covering every exit path. */
    private fun endHardwareOp() = HardwareLock.end()

    fun init() {
        val model = try { Build.DEVICE } catch (_: Exception) { "unknown" }
        val autoEnabled = prefs.getBoolean("auto_fcc", false)
        val keepaliveRunning = FccKeepaliveService.isRunningFlagSet(app)
        update { copy(controllerModel = model, status = "disconnected", autoFcc = autoEnabled, isKeepaliveRunning = keepaliveRunning) }

        if (autoEnabled) {
            log("Otomatik FCC etkin — bağlanılıyor ve uygulanıyor...")
            autoConnectAndApply()
        }

        checkForUpdates()
        startNotificationPolling()
        startTelemetryFlush()
    }

    private fun startTelemetryFlush() {
        runOnIO {
            while (true) {
                delay(60_000)
                try { TelemetryCollector.flushFeatureEvents(app) } catch (_: Exception) {}
            }
        }
    }

    // --- Auto-FCC ---

    /**
     * Toggles auto-FCC on or off. When enabled, the app will automatically
     * connect to the controller and apply FCC mode every time it launches.
     * The setting is saved to SharedPreferences and persists across restarts.
     */
    fun toggleAutoFcc() {
        val newValue = !_state.value.autoFcc
        prefs.edit().putBoolean("auto_fcc", newValue).apply()
        update { copy(autoFcc = newValue) }
        log(if (newValue) "Otomatik FCC etkin — bir sonraki açılışta otomatik bağlanacak" else "Otomatik FCC devre dışı")
        TelemetryCollector.trackFeature("auto_fcc_toggle", true, mapOf("enabled" to newValue))
    }

    /**
     * Connects to the controller and applies FCC mode automatically.
     * Waits for connection, then sends the FCC profile, starts the keepalive
     * service, and launches DJI Fly.
     */
    private fun autoConnectAndApply() {
        if (!beginHardwareOp()) {
            log("Otomatik FCC atlandı — başka bir donanım işlemi zaten çalışıyor")
            return
        }
        runOnIO {
            try {
                // Wait a moment for the UI to render
                delay(1000)

                // Try to connect — scans all known ports
                update { copy(status = "connecting", message = "Otomatik bağlanılıyor...") }
                if (!transport.connect()) {
                    log("Otomatik FCC: kumanda bulunamadı — drone açık mı?")
                    update { copy(status = "disconnected", message = "Kumanda bulunamadı. Bağlan'a dokunduğunuzda Otomatik FCC tekrar deneyecek.") }
                    TelemetryCollector.trackFeature("auto_fcc", false)
                    try { TelemetryCollector.sendFccSession(app, "auto_fcc", false, failureReason = "connect_failed") } catch (_: Exception) {}
                    return@runOnIO
                }

                log("Otomatik FCC: kumandaya bağlandı")
                val detectedPort = transport.getDetectedPort()
                if (detectedPort > 0) {
                    log("DUML portu algılandı: $detectedPort")
                }
                val serial = transport.probeSerial(1500)
                update {
                    copy(
                        status = "connected",
                        isConnected = true,
                        aircraftSerial = serial,
                        message = "Bağlandı. FCC otomatik uygulanıyor..."
                    )
                }
                if (serial.isNotEmpty()) log("Uçak seri no: $serial")

                // Apply FCC
                delay(500)
                update { copy(status = "applying", isBusy = true, busyProgress = 0f, message = "FCC modu uygulanıyor...") }
                log("Otomatik FCC: FCC modu uygulanıyor...")

                val profile = Profiles.load(app, "fcc.json")
                val success = transport.sendFrames(
                    frames = profile.frames,
                    rounds = profile.rounds,
                    interFrameDelayMs = profile.interFrameDelay,
                    interRoundDelayMs = profile.interRoundDelay,
                    readWindowMs = profile.readWindowMs,
                    port = profile.port
                ) { progress -> update { copy(busyProgress = progress) } }

                if (success) {
                    update {
                        copy(
                            status = "fcc_enabled",
                            message = "FCC etkinleştirildi. Canlı tutma başlatılıyor...",
                            isFccEnabled = true,
                            isBusy = false,
                            busyProgress = 1f,
                            isConnected = true
                        )
                    }
                    log("Otomatik FCC: FCC modu etkinleştirildi")
                    TelemetryCollector.markFccStart()
                    TelemetryCollector.trackFeature("auto_fcc", true)
                    try {
                        TelemetryCollector.sendFccSession(app, "auto_fcc", true, aircraftSerial = serial)
                        TelemetryCollector.sendDeviceTelemetry(app, aircraftSerial = serial, detectedPort = detectedPort)
                    } catch (_: Exception) {}

                    // Auto-start keepalive
                    delay(500)
                    update { copy(isKeepaliveRunning = true) }
                    FccKeepaliveService.start(app)
                    log("Otomatik FCC: canlı tutma başlatıldı (her 2 saniyede yeniden uygulanıyor)")
                    TelemetryCollector.trackFeature("keepalive_start", true)
                    try { TelemetryCollector.sendFccSession(app, "keepalive_start", true, aircraftSerial = serial) } catch (_: Exception) {}

                    // Auto-launch DJI Fly
                    delay(500)
                    update { copy(message = "FCC aktif. DJI Fly başlatılıyor...") }
                    log("Otomatik FCC: DJI Fly başlatılıyor")
                    launchDjiFly()
                } else {
                    update {
                        copy(
                            status = "connected",
                            message = "Otomatik FCC başarısız — manuel deneyin",
                            isBusy = false,
                            busyProgress = 0f
                        )
                    }
                    log("Otomatik FCC: uygulama başarısız — manuel deneyin")
                    TelemetryCollector.trackFeature("auto_fcc", false)
                    try { TelemetryCollector.sendFccSession(app, "auto_fcc", false, failureReason = "write_failed", aircraftSerial = serial) } catch (_: Exception) {}
                }
            } catch (e: Exception) {
                log("Otomatik FCC hatası: ${e.message}")
                update { copy(status = "disconnected", message = "Otomatik FCC hatası: ${e.message}", isBusy = false, busyProgress = 0f) }
                try { TelemetryCollector.sendErrorLog(app, "fcc", "Otomatik FCC hatası: ${e.message}", e.stackTraceToString(), "autoConnectAndApply") } catch (_: Exception) {}
            } finally {
                endHardwareOp()
            }
        }
    }

    // --- Connection ---

    /**
     * Connects to the DUML proxy, auto-detecting the correct port.
     * Probes for the aircraft serial number after connecting.
     */
    fun connect() {
        if (!beginHardwareOp()) {
            log("Donanım meşgul — mevcut işlemin bitmesini bekleyin.")
            return
        }
        update { copy(status = "connecting", message = "Kumandaya bağlanılıyor...") }
        log("Kumandaya bağlanılıyor...")

        runOnIO {
            try {
                val connectStart = System.currentTimeMillis()
                if (transport.connect()) {
                    val connectTimeMs = (System.currentTimeMillis() - connectStart).toInt()
                    log("Kumandaya bağlandı")
                    val detectedPort = transport.getDetectedPort()
                    if (detectedPort > 0) {
                        log("DUML portu algılandı: $detectedPort")
                    }
                    val serial = transport.probeSerial(1500)
                    update {
                        copy(
                            status = "connected",
                            message = if (serial.isNotEmpty()) "Bağlandı — $serial" else "Bağlandı. FCC uygulamaya hazır.",
                            isConnected = true,
                            aircraftSerial = serial
                        )
                    }
                    if (serial.isNotEmpty()) log("Uçak seri no: $serial")

                    TelemetryCollector.trackFeature("connect", true, mapOf("port" to detectedPort))
                    try {
                        TelemetryCollector.sendConnectionMetrics(app, connectTimeMs = connectTimeMs, portUsed = detectedPort)
                        TelemetryCollector.sendDeviceTelemetry(app, aircraftSerial = serial, detectedPort = detectedPort)
                    } catch (_: Exception) {}
                } else {
                    update {
                        copy(
                            status = "disconnected",
                            message = "Kumanda bulunamadı. Drone'un açık ve bağlı olduğundan emin olun.",
                            isConnected = false
                        )
                    }
                    log("Bağlantı başarısız — drone açık mı?")
                    TelemetryCollector.trackFeature("connect", false)
                    TelemetryCollector.incrementDisconnections()
                }
            } finally {
                endHardwareOp()
            }
        }
    }

    // --- FCC ---

    /**
     * Sends the 21-frame FCC unlock profile (2 rounds, 150ms between frames).
     * The profile already runs 2 rounds internally for reliability.
     */
    fun enableFcc() {
        if (!beginHardwareOp()) {
            log("Donanım meşgul — mevcut işlemin bitmesini bekleyin.")
            return
        }
        update { copy(status = "applying", isBusy = true, busyProgress = 0f, message = "FCC modu etkinleştiriliyor...") }
        log("FCC modu etkinleştiriliyor...")

        runOnIO {
            try {
                val profile = Profiles.load(app, "fcc.json")
                log("FCC profili yüklendi: ${profile.frames.size} çerçeve, ${profile.rounds} tur")

                val success = transport.sendFrames(
                    frames = profile.frames,
                    rounds = profile.rounds,
                    interFrameDelayMs = profile.interFrameDelay,
                    interRoundDelayMs = profile.interRoundDelay,
                    readWindowMs = profile.readWindowMs,
                    port = profile.port
                ) { progress -> update { copy(busyProgress = progress) } }

                if (success) {
                    update {
                        copy(
                            status = "fcc_enabled",
                            message = "FCC modu etkinleştirildi",
                            isFccEnabled = true,
                            isBusy = false,
                            busyProgress = 1f,
                            isConnected = true
                        )
                    }
                    log("FCC modu etkinleştirildi — ${profile.frames.size} çerçeve gönderildi")
                    TelemetryCollector.markFccStart()
                    TelemetryCollector.trackFeature("fcc_enable", true)
                    try { TelemetryCollector.sendFccSession(app, "fcc_enable", true, aircraftSerial = getOrProbeSerial()) } catch (_: Exception) {}
                } else {
                    update {
                        copy(
                            status = "connected",
                            message = "FCC uygulaması başarısız — RC bağlantısı yok. Drone'un açık ve bağlı olduğundan emin olun.",
                            isBusy = false,
                            busyProgress = 0f
                        )
                    }
                    log("FCC uygulaması başarısız — yazma işlemi başarısız")
                    TelemetryCollector.trackFeature("fcc_enable", false)
                    try { TelemetryCollector.sendFccSession(app, "fcc_enable", false, failureReason = "write_failed") } catch (_: Exception) {}
                }
            } catch (e: Exception) {
                log("FCC uygulama hatası: ${e.message}")
                update { copy(status = "connected", message = "FCC uygulama hatası: ${e.message}", isBusy = false, busyProgress = 0f) }
                try { TelemetryCollector.sendErrorLog(app, "fcc", "FCC uygulama hatası: ${e.message}", e.stackTraceToString(), "enableFcc") } catch (_: Exception) {}
            } finally {
                endHardwareOp()
            }
        }
    }

    /** Sends the CE restore command: a single frame that resets to factory region. */
    fun disableFcc() {
        if (!beginHardwareOp()) {
            log("Donanım meşgul — mevcut işlemin bitmesini bekleyin.")
            return
        }
        // Stop keepalive first — otherwise it re-applies FCC 2 seconds after
        // we restore CE, undoing the user's intent.
        if (_state.value.isKeepaliveRunning) {
            stopKeepalive()
        }
        update { copy(status = "restoring", isBusy = true, busyProgress = 0f, message = "CE modu geri yükleniyor...") }
        log("CE modu geri yükleniyor...")

        runOnIO {
            try {
                val profile = Profiles.load(app, "ce_restore.json")
                val success = transport.sendFrames(
                    frames = profile.frames,
                    rounds = profile.rounds,
                    readWindowMs = profile.readWindowMs
                )

                if (success) {
                    update { copy(status = "connected", message = "CE modu geri yüklendi", isFccEnabled = false, isBusy = false) }
                    log("CE modu geri yüklendi")
                    TelemetryCollector.trackFeature("fcc_disable", true)
                    try { TelemetryCollector.sendFccSession(app, "fcc_disable", true, aircraftSerial = getOrProbeSerial()) } catch (_: Exception) {}
                } else {
                    update { copy(status = "connected", message = "CE geri yükleme başarısız — RC bağlantısı yok", isBusy = false) }
                    log("CE geri yükleme başarısız")
                    TelemetryCollector.trackFeature("fcc_disable", false)
                    try { TelemetryCollector.sendFccSession(app, "fcc_disable", false, failureReason = "write_failed") } catch (_: Exception) {}
                }
            } catch (e: Exception) {
                log("CE geri yükleme hatası: ${e.message}")
                update { copy(status = "connected", message = "CE geri yükleme hatası: ${e.message}", isBusy = false) }
                try { TelemetryCollector.sendErrorLog(app, "fcc", "CE geri yükleme hatası: ${e.message}", e.stackTraceToString(), "disableFcc") } catch (_: Exception) {}
            } finally {
                endHardwareOp()
            }
        }
    }

    // --- FCC Keepalive ---

    /**
     * Starts a foreground service that re-applies the FCC profile every 2 seconds.
     * This prevents DJI Fly from resetting the radio back to CE mode when it
     * connects to the drone. The service runs independently of the Activity
     * lifecycle so it keeps working when the user switches to DJI Fly.
     */
    fun startKeepalive() {
        if (_state.value.isKeepaliveRunning) {
            log("Canlı tutma zaten çalışıyor")
            return
        }
        update { copy(isKeepaliveRunning = true) }
        FccKeepaliveService.start(app)
        log("FCC canlı tutma başlatıldı — CE sıfırlamasını önlemek için her 2 saniyede yeniden uygulanıyor")
        TelemetryCollector.trackFeature("keepalive_start", true)
        runOnIO { try { TelemetryCollector.sendFccSession(app, "keepalive_start", true, aircraftSerial = _state.value.aircraftSerial) } catch (_: Exception) {} }
    }

    /** Stops the keepalive foreground service. */
    fun stopKeepalive() {
        FccKeepaliveService.stop(app)
        update { copy(isKeepaliveRunning = false) }
        log("FCC canlı tutma durduruldu")
        TelemetryCollector.trackFeature("keepalive_stop", true)
        runOnIO { try { TelemetryCollector.sendFccSession(app, "keepalive_stop", true, aircraftSerial = _state.value.aircraftSerial) } catch (_: Exception) {} }
    }

    // --- Launch DJI Fly ---

    /**
     * Launches the DJI Fly app (dji.go.v5) so the user can continue flying
     * with FCC mode active. The keepalive service keeps re-applying FCC in the
     * background while DJI Fly runs.
     */
    fun launchDjiFly() {
        val pm = app.packageManager
        // Try the standard launch intent first
        var intent = pm.getLaunchIntentForPackage("dji.go.v5")
        if (intent != null) {
            intent.addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK)
            try {
                app.startActivity(intent)
                log("DJI Fly başlatıldı")
                TelemetryCollector.trackFeature("dji_fly_launch", true, mapOf("package" to "dji.go.v5"))
                return
            } catch (_: Exception) {}
        }

        // Fallback: try explicit component — DJI Fly's main activity
        for (activityName in listOf(
            "dji.pilot2.lite.LauncherActivity",
            "dji.go.v5.MainActivity",
            "dji.pilot2.lite.LiteLauncherActivity",
            "dji.go.v5.SplashActivity"
        )) {
            val explicitIntent = android.content.Intent().apply {
                component = android.content.ComponentName("dji.go.v5", activityName)
                addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            try {
                app.startActivity(explicitIntent)
                log("DJI Fly başlatıldı")
                TelemetryCollector.trackFeature("dji_fly_launch", true, mapOf("activity" to activityName))
                return
            } catch (_: Exception) {}
        }

        // Fallback 2: try dji.go.v4
        intent = pm.getLaunchIntentForPackage("dji.go.v4")
        if (intent != null) {
            intent.addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK)
            try {
                app.startActivity(intent)
                log("DJI Go 4 başlatıldı")
                TelemetryCollector.trackFeature("dji_fly_launch", true, mapOf("package" to "dji.go.v4"))
                return
            } catch (_: Exception) {}
        }

        log("DJI Fly yüklü değil veya bu kumandada başlatılamıyor")
        TelemetryCollector.trackFeature("dji_fly_launch", false)
    }

    // --- 4G ---

    /**
     * Sends the 128-frame 4G activation profile.
     * The aircraft serial is embedded in each frame's payload at runtime.
     * 4G frames are sent via Unix domain socket (/duss/mb/0x205), not TCP.
     *
     * The socket does not respond, so this can only confirm the frames were
     * written — never confirm the aircraft actually activated 4G. There is
     * no "off" action: no send-only command exists to reliably deactivate it.
     */
    fun send4gActivationFrames() {
        if (!beginHardwareOp()) {
            log("Donanım meşgul — mevcut işlemin bitmesini bekleyin.")
            return
        }
        update { copy(is4gBusy = true, busyProgress = 0f, fourGMessage = "") }
        log("4G etkinleştirme çerçeveleri gönderiliyor...")

        runOnIO {
            try {
                val serial = getOrProbeSerial()
                if (serial.isEmpty()) {
                    update {
                        copy(is4gBusy = false, fourGMessage = "4G için uçağın bağlı olması gerekir. Drone'u açın ve tekrar deneyin.")
                    }
                    log("4G etkinleştirme başarısız — uçak seri no yok")
                    return@runOnIO
                }

                val profile = Profiles.load4g(app, serial)
                log("4G profili yüklendi: ${profile.frames.size} çerçeve (seri no: $serial)")

                // 4G uses Unix domain socket, not TCP
                val success = transport.sendFramesUnix(
                    frames = profile.frames,
                    interFrameDelayMs = profile.interFrameDelay
                ) { progress -> update { copy(busyProgress = progress) } }

                if (success) {
                    update {
                        copy(
                            is4gBusy = false,
                            busyProgress = 0f,
                            fourGMessage = "Tüm etkinleştirme çerçeveleri başarıyla yazıldı — uçaktaki 4G durumunu kontrol edin."
                        )
                    }
                    log("4G etkinleştirme: tüm ${profile.frames.size} çerçeve Unix soketi üzerinden başarıyla yazıldı")
                    TelemetryCollector.trackFeature("4g_activate", true, mapOf("serial" to serial))
                } else {
                    update { copy(is4gBusy = false, fourGMessage = "4G uygulaması başarısız — 4G dongle bağlı mı?") }
                    log("4G etkinleştirme başarısız — Unix soketinde en az bir çerçeve yazılamadı")
                    TelemetryCollector.trackFeature("4g_activate", false)
                }
            } catch (e: Exception) {
                log("4G etkinleştirme hatası: ${e.message}")
                update { copy(is4gBusy = false, fourGMessage = "4G hatası: ${e.message}") }
                try { TelemetryCollector.sendErrorLog(app, "4g", "4G hatası: ${e.message}", e.stackTraceToString(), "send4g") } catch (_: Exception) {}
            } finally {
                endHardwareOp()
            }
        }
    }

    // --- LED ---

    /**
     * Turns the aircraft arm LEDs on or off.
     * Uses port 40007 (different from the standard 40009 DUML port).
     * Requires DJI Fly running with the aircraft connected.
     * Sends the command twice with a 500ms delay for reliability.
     *
     * @param on true for LED ON, false for LED OFF
     */
    fun setLed(on: Boolean) {
        if (!beginHardwareOp()) {
            log("Donanım meşgul — mevcut işlemin bitmesini bekleyin.")
            return
        }
        update { copy(isLedBusy = true, ledStatus = if (on) "LED'ler açılıyor..." else "LED'ler kapatılıyor...") }
        log(if (on) "LED'ler açılıyor..." else "LED'ler kapatılıyor...")

        runOnIO {
            try {
                val fileName = if (on) "led_on.json" else "led_off.json"
                val profile = Profiles.load(app, fileName)
                log("LED profili yüklendi: ${profile.frames.size} çerçeve (port ${profile.port})")

                var anySuccess = false

                // Send the LED command twice with a 500ms delay for reliability
                for (attempt in 0 until 2) {
                    if (attempt > 0) {
                        delay(500)
                    }

                    val success = transport.sendFrames(
                        frames = profile.frames,
                        rounds = profile.rounds,
                        interFrameDelayMs = profile.interFrameDelay,
                        interRoundDelayMs = profile.interRoundDelay,
                        readWindowMs = profile.readWindowMs,
                        port = profile.port
                    )

                    if (success) anySuccess = true
                }

                val featureName = if (on) "led_on" else "led_off"
                if (anySuccess) {
                    update { copy(isLedBusy = false, ledStatus = if (on) "AÇIK" else "KAPALI") }
                    log(if (on) "LED'ler açıldı" else "LED'ler kapatıldı")
                    TelemetryCollector.trackFeature(featureName, true)
                } else {
                    update { copy(isLedBusy = false, ledStatus = "Başarısız — DJI Fly çalışıyor mu?") }
                    log("LED komutu başarısız — DJI Fly'ın uçak bağlıyken çalıştığından emin olun")
                    TelemetryCollector.trackFeature(featureName, false)
                }
            } catch (e: Exception) {
                log("LED hatası: ${e.message}")
                update { copy(isLedBusy = false, ledStatus = "Hata: ${e.message}") }
                try { TelemetryCollector.sendErrorLog(app, "led", "LED hatası: ${e.message}", e.stackTraceToString(), "setLed") } catch (_: Exception) {}
            } finally {
                endHardwareOp()
            }
        }
    }

    // --- Device Info ---

    /**
     * Queries the controller for hardware version, bootloader version, and
     * firmware version via the GENERAL VersionInquiry command
     * (cmd_set=0, cmd_id=1). Uses sendAndReceive to capture the response.
     */
    fun queryDeviceInfo() {
        if (!isControllerReachable()) return
        if (!beginHardwareOp()) {
            log("Donanım meşgul — mevcut işlemin bitmesini bekleyin.")
            return
        }

        update { copy(isQueryingInfo = true) }
        log("Cihaz bilgisi sorgulanıyor...")

        runOnIO {
            try {
                val profile = Profiles.load(app, "device_info.json")
                if (profile.frames.isEmpty()) {
                    update { copy(isQueryingInfo = false, deviceInfo = "device_info.json boş") }
                    log("Cihaz bilgisi: profilde çerçeve yok")
                    return@runOnIO
                }
                val frame = profile.frames.first()

                val latencyStart = System.currentTimeMillis()
                val response = transport.sendAndReceive(frame, profile.readWindowMs)
                val commandLatencyMs = (System.currentTimeMillis() - latencyStart).toInt()

                if (response == null || response.isEmpty()) {
                    update { copy(isQueryingInfo = false, deviceInfo = "Kumandadan yanıt alınamadı") }
                    log("Cihaz bilgisi: yanıt yok")
                    TelemetryCollector.trackFeature("device_info", false)
                    try {
                        TelemetryCollector.sendConnectionMetrics(
                            app,
                            commandLatencyMs = commandLatencyMs,
                            portUsed = transport.getDetectedPort()
                        )
                    } catch (_: Exception) {}
                    return@runOnIO
                }

                val info = formatVersionResponse(response)
                update { copy(isQueryingInfo = false, deviceInfo = info) }
                log("Cihaz bilgisi alındı: ${response.size} bayt")
                TelemetryCollector.trackFeature("device_info", true)

                val fwVersion = if (response.size >= 26) formatVersion(readUInt32LE(response, 22)) else null
                val hwVersion = if (response.size >= 18) String(response, 2, 16, Charsets.US_ASCII).trimEnd('\u0000') else null
                val blVersion = if (response.size >= 22) formatVersion(readUInt32LE(response, 18)) else null
                try {
                    TelemetryCollector.sendConnectionMetrics(
                        app,
                        commandLatencyMs = commandLatencyMs,
                        portUsed = transport.getDetectedPort()
                    )
                    TelemetryCollector.sendDeviceTelemetry(
                        app,
                        firmwareVersion = fwVersion,
                        hardwareVersion = hwVersion,
                        bootloaderVersion = blVersion,
                        aircraftSerial = _state.value.aircraftSerial,
                        detectedPort = transport.getDetectedPort()
                    )
                } catch (_: Exception) {}
            } catch (e: Exception) {
                log("Cihaz bilgisi hatası: ${e.message}")
                update { copy(isQueryingInfo = false, deviceInfo = "Hata: ${e.message}") }
                TelemetryCollector.trackFeature("device_info", false)
                try { TelemetryCollector.sendErrorLog(app, "duml", "Cihaz bilgisi hatası: ${e.message}", e.stackTraceToString(), "queryDeviceInfo") } catch (_: Exception) {}
            } finally {
                endHardwareOp()
            }
        }
    }

    fun probeSerial() {
        if (!beginHardwareOp()) {
            log("Donanım meşgul — mevcut işlemin bitmesini bekleyin.")
            return
        }
        log("Uçak seri no sorgulanıyor...")
        runOnIO {
            try {
                val serial = transport.probeSerial(2000)
                if (serial.isNotEmpty()) {
                    update { copy(aircraftSerial = serial) }
                    log("Uçak seri no: $serial")
                } else {
                    log("Seri no algılanmadı — uçak açık mı?")
                }
            } finally {
                endHardwareOp()
            }
        }
    }

    // --- Updates ---

    companion object {
        const val APP_VERSION = "1.4.9"
        const val APP_VERSION_CODE = 18
    }

    fun checkForUpdates() {
        val lastCheck = prefs.getLong("last_update_check", 0)
        val now = System.currentTimeMillis()
        if (now - lastCheck < 5 * 60 * 1000 && _state.value.updateChecked && !_state.value.updateCheckFailed) {
            return
        }
        prefs.edit().putLong("last_update_check", now).apply()
        update { copy(isCheckingUpdate = true, updateCheckFailed = false) }
        log("Güncellemeler kontrol ediliyor...")

        runOnIO {
            when (val result = UpdateChecker.fetchLatestResult(APP_VERSION, APP_VERSION_CODE)) {
                is UpdateCheckResult.Failed -> {
                    update { copy(isCheckingUpdate = false, updateChecked = true, updateCheckFailed = true) }
                    log("Güncelleme kontrolü başarısız — internet bağlantınızı kontrol edin")
                }
                is UpdateCheckResult.UpToDate -> {
                    update { copy(isCheckingUpdate = false, updateChecked = true, updateCheckFailed = false, updateAvailable = false) }
                    log("Uygulama güncel (v$APP_VERSION)")
                }
                is UpdateCheckResult.Available -> {
                    val info = result.info
                    val isNewer = info.isNewerThan(APP_VERSION_CODE)
                    update {
                        copy(
                            updateInfo = info,
                            isCheckingUpdate = false,
                            updateChecked = true,
                            updateCheckFailed = false,
                            updateAvailable = isNewer,
                            isUpdateForced = isNewer && info.isForced
                        )
                    }
                    if (isNewer) {
                        log(if (info.isForced) "Zorunlu güncelleme mevcut: v${info.version}" else "Güncelleme mevcut: v${info.version}")
                    } else {
                        log("Uygulama güncel (v$APP_VERSION)")
                    }
                }
            }
        }
    }

    private var downloadedApk: java.io.File? = null
    private var pendingInstallAfterPermission = false

    fun downloadUpdate() {
        val info = _state.value.updateInfo ?: return
        if (_state.value.isDownloadingUpdate) return
        update { copy(isDownloadingUpdate = true, updateDownloadProgress = 0f, isUpdateDownloaded = false) }
        log("Güncelleme v${info.version} indiriliyor...")

        runOnIO {
            when (val result = UpdateChecker.downloadApk(app, info) { progress ->
                update { copy(updateDownloadProgress = progress) }
            }) {
                is UpdateChecker.DownloadResult.Err -> {
                    update { copy(isDownloadingUpdate = false, updateDownloadProgress = 0f) }
                    log("Güncelleme indirme başarısız: ${result.message}")
                }
                is UpdateChecker.DownloadResult.Ok -> {
                    downloadedApk = result.file
                    update {
                        copy(
                            isDownloadingUpdate = false,
                            updateDownloadProgress = 1f,
                            isUpdateDownloaded = true
                        )
                    }
                    log("Güncelleme indirildi — yükleyici açılıyor...")
                    withContext(Dispatchers.Main) {
                        installUpdate()
                    }
                }
            }
        }
    }

    fun canInstallPackages(): Boolean {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            app.packageManager.canRequestPackageInstalls()
        } else {
            true
        }
    }

    fun openInstallPermissionSettings() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        pendingInstallAfterPermission = true
        update { copy(needsInstallPermission = true, message = "Bilinmeyen uygulamalardan yükleme izni verin") }
        val intent = Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES).apply {
            data = Uri.parse("package:${app.packageName}")
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        try {
            app.startActivity(intent)
            log("Kurulum izni için Ayarlar açıldı — izin verip geri dönün")
        } catch (e: Exception) {
            log("Ayarlar açılamadı: ${e.message}")
        }
    }

    /** Call from Activity.onResume so install continues after the user grants permission. */
    fun onAppResumed() {
        if (!pendingInstallAfterPermission) return
        if (!canInstallPackages()) return
        pendingInstallAfterPermission = false
        update { copy(needsInstallPermission = false) }
        installUpdate()
    }

    fun installUpdate() {
        val file = downloadedApk ?: run {
            log("İndirilen APK bulunamadı — önce indirin")
            return
        }
        if (!file.exists()) {
            log("İndirilen APK dosyası eksik — tekrar indirin")
            downloadedApk = null
            update { copy(isUpdateDownloaded = false) }
            return
        }

        if (!canInstallPackages()) {
            openInstallPermissionSettings()
            return
        }

        update {
            copy(
                isBusy = true,
                needsInstallPermission = false,
                message = "Yükleme hazırlanıyor..."
            )
        }
        viewModelScope.launch(Dispatchers.Main) {
            try {
                // Use the cached APK directly via FileProvider — no external storage
                // copy needed (scoped storage on Android 10+ blocks writing to the
                // root of external storage without MANAGE_EXTERNAL_STORAGE, which
                // we don't have). The cache-path "updates/" in file_paths.xml
                // covers cacheDir/updates/ where the APK already lives.
                val uri = androidx.core.content.FileProvider.getUriForFile(
                    app, "${app.packageName}.fileprovider", file
                )
                val viewIntent = Intent(Intent.ACTION_VIEW).apply {
                    setDataAndType(uri, "application/vnd.android.package-archive")
                    addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                    addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                }
                app.startActivity(viewIntent)
                log("Yükleyici başlatılıyor...")
            } catch (e: Exception) {
                log("Yükleme başarısız: ${e.message}")
            } finally {
                update { copy(isBusy = false) }
            }
        }
    }

    // --- Notifications ---

    fun startNotificationPolling() {
        runOnIO {
            while (true) {
                delay(30_000)
                pollNotifications()
            }
        }
    }

    private fun pollNotifications() {
        val token = AuthManager.getToken(app) ?: return
        val lastSeenId = NotificationPoller.getLastSeenId(app)
        val newNotifs = NotificationPoller.fetchNew(token, lastSeenId)
        if (newNotifs.isNotEmpty()) {
            update {
                copy(
                    pendingNotifications = pendingNotifications + newNotifs,
                    showNotificationDialog = true,
                    currentNotification = currentNotification ?: newNotifs.first()
                )
            }
            val maxId = newNotifs.maxOf { it.id }
            NotificationPoller.saveLastSeenId(app, maxId)
            for (notif in newNotifs) {
                NotificationHelper.showNotification(app, notif)
            }
        }
    }

    fun dismissNotification() {
        val remaining = _state.value.pendingNotifications.drop(1)
        update {
            copy(
                pendingNotifications = remaining,
                showNotificationDialog = remaining.isNotEmpty(),
                currentNotification = remaining.firstOrNull()
            )
        }
    }

    fun dismissAllNotifications() {
        update {
            copy(
                pendingNotifications = emptyList(),
                showNotificationDialog = false,
                currentNotification = null
            )
        }
    }

    // --- Helpers ---

    /** Returns true if the controller is connected, logs a hint if not. */
    private fun isControllerReachable(): Boolean {
        if (_state.value.isConnected) return true
        log("Önce kumandaya bağlanın")
        return false
    }

    /** Returns the cached serial, or probes the controller if not yet known. */
    private fun getOrProbeSerial(): String {
        var serial = _state.value.aircraftSerial
        if (serial.isEmpty()) {
            log("Uçak seri no sorgulanıyor...")
            serial = transport.probeSerial(2000)
            if (serial.isNotEmpty()) {
                update { copy(aircraftSerial = serial) }
                log("Uçak seri no: $serial")
            }
        }
        return serial
    }

    /**
     * Parses a DUML VersionInquiry response payload into a human-readable string.
     *
     * Response layout (from dji-firmware-tools DJIPayload_General_VersionInquiryRe):
     *   byte  0-1    unknown
     *   bytes 2-17   hardware version (16-char ASCII string)
     *   bytes 18-21  bootloader version (uint32 LE)
     *   bytes 22-25  firmware version (uint32 LE)
     */
    private fun formatVersionResponse(payload: ByteArray): String {
        val lines = mutableListOf<String>()

        if (payload.size >= 18) {
            val hwVersion = String(payload, 2, 16, Charsets.US_ASCII).trimEnd('\u0000')
            lines.add("Donanım: $hwVersion")
        }

        if (payload.size >= 22) {
            val ldrVersion = readUInt32LE(payload, 18)
            lines.add("Önyükleyici: ${formatVersion(ldrVersion)}")
        }

        if (payload.size >= 26) {
            val appVersion = readUInt32LE(payload, 22)
            lines.add("Firmware: ${formatVersion(appVersion)}")
        }

        lines.add("")
        lines.add("Ham veri (${payload.size} bayt):")
        lines.add(payload.joinToString(" ") { "%02x".format(it) })

        return lines.joinToString("\n")
    }

    /** Reads a 32-bit little-endian unsigned integer from a byte array. */
    private fun readUInt32LE(data: ByteArray, offset: Int): Long {
        return ((data[offset].toLong() and 0xFF)) or
               ((data[offset + 1].toLong() and 0xFF) shl 8) or
               ((data[offset + 2].toLong() and 0xFF) shl 16) or
               ((data[offset + 3].toLong() and 0xFF) shl 24)
    }

    /** Formats a DJI firmware version uint32 as major.minor.patch.build. */
    private fun formatVersion(version: Long): String {
        val major = (version shr 24) and 0xFF
        val minor = (version shr 16) and 0xFF
        val patch = (version shr 8) and 0xFF
        val build = version and 0xFF
        return "$major.$minor.$patch.$build"
    }

    /** Atomically updates the state via a copy() block. */
    private fun update(block: AppState.() -> AppState) {
        _state.value = _state.value.block()
    }

    /** Adds a timestamped entry to the activity log (most recent first, max 50). */
    private fun log(message: String) {
        val time = SimpleDateFormat("HH:mm:ss", Locale.US).format(Date())
        val entry = "[$time] $message"
        update { copy(logMessages = (listOf(entry) + logMessages).take(50)) }
    }

    /** Launches a coroutine on Dispatchers.IO for network operations. */
    private fun runOnIO(block: suspend () -> Unit) {
        viewModelScope.launch(Dispatchers.IO) { block() }
    }
}