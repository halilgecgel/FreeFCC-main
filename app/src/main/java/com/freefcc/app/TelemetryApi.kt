package com.freefcc.app

import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

object TelemetryApi {

    private fun post(path: String, body: JSONObject, token: String): Boolean {
        var conn: HttpURLConnection? = null
        return try {
            conn = (URL(AuthApi.BASE_URL + path).openConnection() as HttpURLConnection).apply {
                requestMethod = "POST"
                connectTimeout = 10000
                readTimeout = 10000
                setRequestProperty("Accept", "application/json")
                setRequestProperty("Content-Type", "application/json")
                setRequestProperty("Authorization", "Bearer $token")
                setRequestProperty("User-Agent", "FreeFCC-App")
                doOutput = true
            }
            conn.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
            conn.responseCode in 200..299
        } catch (_: Exception) {
            false
        } finally {
            conn?.disconnect()
        }
    }

    fun sendDeviceTelemetry(
        token: String,
        controllerModel: String?,
        androidVersion: String?,
        firmwareVersion: String?,
        hardwareVersion: String?,
        bootloaderVersion: String?,
        aircraftSerial: String?,
        droneModel: String?,
        detectedPort: Int?,
        appVersion: String?,
        networkType: String? = null,
        countryCode: String? = null,
        locale: String? = null,
        latitude: Double? = null,
        longitude: Double? = null,
        serverPingMs: Int? = null
    ): Boolean {
        val body = JSONObject().apply {
            put("controller_model", controllerModel ?: JSONObject.NULL)
            put("android_version", androidVersion ?: JSONObject.NULL)
            put("firmware_version", firmwareVersion ?: JSONObject.NULL)
            put("hardware_version", hardwareVersion ?: JSONObject.NULL)
            put("bootloader_version", bootloaderVersion ?: JSONObject.NULL)
            put("aircraft_serial", aircraftSerial ?: JSONObject.NULL)
            put("drone_model", droneModel ?: JSONObject.NULL)
            put("detected_port", detectedPort ?: JSONObject.NULL)
            put("app_version", appVersion ?: JSONObject.NULL)
            put("network_type", networkType ?: JSONObject.NULL)
            put("country_code", countryCode ?: JSONObject.NULL)
            put("locale", locale ?: JSONObject.NULL)
            put("latitude", latitude ?: JSONObject.NULL)
            put("longitude", longitude ?: JSONObject.NULL)
            put("server_ping_ms", serverPingMs ?: JSONObject.NULL)
        }
        return post("/telemetry/device", body, token)
    }

    fun sendFccSession(
        token: String,
        action: String,
        success: Boolean,
        durationSeconds: Int? = null,
        keepaliveCount: Int? = null,
        ceResetBlocks: Int? = null,
        aircraftSerial: String? = null,
        controllerModel: String? = null,
        deviceModel: String? = null,
        latitude: Double? = null,
        longitude: Double? = null,
        province: String? = null,
        district: String? = null,
        neighborhood: String? = null,
        failureReason: String? = null
    ): Boolean {
        val body = JSONObject().apply {
            put("action", action)
            put("success", success)
            put("duration_seconds", durationSeconds ?: JSONObject.NULL)
            put("keepalive_count", keepaliveCount ?: JSONObject.NULL)
            put("ce_reset_blocks", ceResetBlocks ?: JSONObject.NULL)
            put("aircraft_serial", aircraftSerial ?: JSONObject.NULL)
            put("controller_model", controllerModel ?: JSONObject.NULL)
            put("device_model", deviceModel ?: JSONObject.NULL)
            put("latitude", latitude ?: JSONObject.NULL)
            put("longitude", longitude ?: JSONObject.NULL)
            put("province", province ?: JSONObject.NULL)
            put("district", district ?: JSONObject.NULL)
            put("neighborhood", neighborhood ?: JSONObject.NULL)
            put("failure_reason", failureReason ?: JSONObject.NULL)
        }
        return post("/telemetry/fcc-session", body, token)
    }

    fun sendFeatureUsage(
        token: String,
        feature: String,
        success: Boolean,
        metadata: Map<String, Any?>? = null
    ): Boolean {
        val body = JSONObject().apply {
            put("feature", feature)
            put("success", success)
            if (metadata != null) {
                put("metadata", JSONObject(metadata))
            }
        }
        return post("/telemetry/feature-usage", body, token)
    }

    fun sendFeatureUsageBatch(
        token: String,
        events: List<FeatureEvent>
    ): Boolean {
        if (events.isEmpty()) return true
        val arr = JSONArray()
        for (event in events) {
            arr.put(JSONObject().apply {
                put("feature", event.feature)
                put("success", event.success)
                if (event.metadata != null) {
                    put("metadata", JSONObject(event.metadata))
                }
            })
        }
        val body = JSONObject().apply { put("events", arr) }
        return post("/telemetry/feature-usage/batch", body, token)
    }

    fun sendErrorLog(
        token: String,
        errorType: String,
        message: String,
        stackTrace: String? = null,
        context: String? = null,
        appVersion: String? = null,
        controllerModel: String? = null
    ): Boolean {
        val body = JSONObject().apply {
            put("error_type", errorType)
            put("message", message)
            put("stack_trace", stackTrace ?: JSONObject.NULL)
            put("context", context ?: JSONObject.NULL)
            put("app_version", appVersion ?: JSONObject.NULL)
            put("controller_model", controllerModel ?: JSONObject.NULL)
        }
        return post("/telemetry/error", body, token)
    }

    fun sendConnectionMetrics(
        token: String,
        connectTimeMs: Int? = null,
        commandLatencyMs: Int? = null,
        disconnectionCount: Int? = null,
        crcErrorCount: Int? = null,
        portUsed: Int? = null,
        controllerModel: String? = null
    ): Boolean {
        val body = JSONObject().apply {
            put("connect_time_ms", connectTimeMs ?: JSONObject.NULL)
            put("command_latency_ms", commandLatencyMs ?: JSONObject.NULL)
            put("disconnection_count", disconnectionCount ?: JSONObject.NULL)
            put("crc_error_count", crcErrorCount ?: JSONObject.NULL)
            put("port_used", portUsed ?: JSONObject.NULL)
            put("controller_model", controllerModel ?: JSONObject.NULL)
        }
        return post("/telemetry/connection-metrics", body, token)
    }

    /** Measures round-trip time to the heartbeat endpoint. Returns ms or null. */
    fun measureServerPing(token: String): Int? {
        var conn: HttpURLConnection? = null
        return try {
            val start = System.currentTimeMillis()
            conn = (URL(AuthApi.BASE_URL + "/heartbeat").openConnection() as HttpURLConnection).apply {
                requestMethod = "POST"
                connectTimeout = 8000
                readTimeout = 8000
                setRequestProperty("Accept", "application/json")
                setRequestProperty("Content-Type", "application/json")
                setRequestProperty("Authorization", "Bearer $token")
                setRequestProperty("User-Agent", "FreeFCC-App")
                doOutput = true
            }
            conn.outputStream.use { it.write("{}".toByteArray(Charsets.UTF_8)) }
            val code = conn.responseCode
            if (code in 200..299) (System.currentTimeMillis() - start).toInt() else null
        } catch (_: Exception) {
            null
        } finally {
            conn?.disconnect()
        }
    }
}

data class FeatureEvent(
    val feature: String,
    val success: Boolean,
    val metadata: Map<String, Any?>? = null
)
