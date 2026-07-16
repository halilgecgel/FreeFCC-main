package com.freefcc.app

import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

/**
 * Talks to the FreeFCC Laravel backend (see /backend in the repo root) to
 * authenticate app members. Mirrors [UpdateChecker]'s style — plain
 * HttpURLConnection + org.json, no extra networking dependency.
 */

data class DeviceModelInfo(
    val id: Long,
    val name: String,
    val slug: String,
    val description: String? = null
)

data class MemberInfo(
    val username: String,
    val name: String?,
    val expiresAt: String?,
    val deviceModel: DeviceModelInfo? = null
)

data class LoginData(
    val token: String,
    val member: MemberInfo
)

/** Machine-readable error codes returned by the backend — see backend/README.md. */
object AuthErrorCode {
    const val INVALID_CREDENTIALS = "invalid_credentials"
    const val INACTIVE = "inactive"
    const val EXPIRED = "expired"
    const val DEVICE_MISMATCH = "device_mismatch"
    const val ALREADY_SELECTED = "already_selected"
    const val INVALID_MODEL = "invalid_model"
    const val NETWORK = "network_error"
    const val UNKNOWN = "unknown_error"
}

data class AuthError(val code: String, val message: String)

sealed class AuthResult<out T> {
    data class Success<T>(val data: T) : AuthResult<T>()
    data class Failure(val error: AuthError) : AuthResult<Nothing>()
}

object AuthApi {

    // Tek kaynak: uygulamadaki tüm ağ istekleri (UpdateChecker, NotificationPoller, vs.)
    // bu sabiti kullanır — URL'yi değiştirmek gerektiğinde sadece burası güncellenir.
    const val BASE_URL = "https://fcc.gecgelsoft.com/api/v1"

    fun login(
        username: String,
        password: String,
        deviceId: String,
        deviceName: String,
        appVersion: String
    ): AuthResult<LoginData> {
        val body = JSONObject().apply {
            put("username", username)
            put("password", password)
            put("device_id", deviceId)
            put("device_name", deviceName)
            put("app_version", appVersion)
        }

        val (code, json) = request("POST", "/login", body, token = null)
            ?: return networkError()

        if (code == 200 && json != null) {
            val data = json.optJSONObject("data") ?: return unknownError()
            val member = data.optJSONObject("member") ?: return unknownError()
            return AuthResult.Success(
                LoginData(
                    token = data.optString("token"),
                    member = parseMember(member)
                )
            )
        }

        return AuthResult.Failure(parseError(json))
    }

    /** Verifies the token is still valid and the account is still active/not expired. */
    fun me(token: String): AuthResult<MemberInfo> {
        val (code, json) = request("GET", "/me", body = null, token = token)
            ?: return networkError()

        if (code == 200 && json != null) {
            val data = json.optJSONObject("data") ?: return unknownError()
            val member = data.optJSONObject("member") ?: return unknownError()
            return AuthResult.Success(parseMember(member))
        }

        return AuthResult.Failure(parseError(json))
    }

    fun listDeviceModels(token: String): AuthResult<List<DeviceModelInfo>> {
        val (code, json) = request("GET", "/device-models", body = null, token = token)
            ?: return networkError()

        if (code == 200 && json != null) {
            val data = json.optJSONObject("data") ?: return unknownError()
            val arr = data.optJSONArray("device_models") ?: return unknownError()
            val models = buildList {
                for (i in 0 until arr.length()) {
                    val obj = arr.optJSONObject(i) ?: continue
                    add(
                        DeviceModelInfo(
                            id = obj.optLong("id"),
                            name = obj.optString("name"),
                            slug = obj.optString("slug"),
                            description = obj.optString("description", "").ifEmpty { null }
                        )
                    )
                }
            }
            return AuthResult.Success(models)
        }

        return AuthResult.Failure(parseError(json))
    }

    fun selectDeviceModel(token: String, deviceModelId: Long): AuthResult<MemberInfo> {
        val body = JSONObject().apply {
            put("device_model_id", deviceModelId)
        }

        val (code, json) = request("POST", "/me/device-model", body, token = token)
            ?: return networkError()

        if (code == 200 && json != null) {
            val data = json.optJSONObject("data") ?: return unknownError()
            val member = data.optJSONObject("member") ?: return unknownError()
            return AuthResult.Success(parseMember(member))
        }

        return AuthResult.Failure(parseError(json))
    }

    /** Sends a heartbeat to keep the member marked as online on the server. */
    fun heartbeat(token: String): Boolean {
        return try {
            val (code, _) = request("POST", "/heartbeat", body = null, token = token)
                ?: return false
            code == 200
        } catch (_: Exception) {
            false
        }
    }

    /** Marks the member offline without revoking the token. Called on app background/close. */
    fun goOffline(token: String) {
        try {
            request("POST", "/offline", body = null, token = token)
        } catch (_: Exception) {
        }
    }

    /** Revokes the current token server-side. Best-effort — ignore failures, we log out locally regardless. */
    fun logout(token: String) {
        try {
            request("POST", "/logout", body = null, token = token)
        } catch (_: Exception) {
        }
    }

    private fun parseMember(member: JSONObject): MemberInfo {
        val modelObj = member.optJSONObject("device_model")
        val deviceModel = if (modelObj != null && modelObj.optLong("id") > 0) {
            DeviceModelInfo(
                id = modelObj.optLong("id"),
                name = modelObj.optString("name"),
                slug = modelObj.optString("slug"),
                description = modelObj.optString("description", "").ifEmpty { null }
            )
        } else {
            null
        }

        return MemberInfo(
            username = member.optString("username"),
            name = member.optString("name", "").ifEmpty { null },
            expiresAt = member.optString("expires_at", "").ifEmpty { null },
            deviceModel = deviceModel
        )
    }

    private fun unknownError() = AuthResult.Failure(
        AuthError(AuthErrorCode.UNKNOWN, "Sunucudan beklenmeyen bir yanıt alındı.")
    )

    private fun networkError() = AuthResult.Failure(
        AuthError(AuthErrorCode.NETWORK, "Sunucuya bağlanılamadı. İnternet bağlantınızı kontrol edin.")
    )

    private fun parseError(json: JSONObject?): AuthError {
        val code = json?.optString("code")?.ifEmpty { null } ?: AuthErrorCode.UNKNOWN
        val message = json?.optString("message")?.ifEmpty { null }
            ?: "Bilinmeyen bir hata oluştu. Lütfen tekrar deneyin."
        return AuthError(code, message)
    }

    /** Returns (httpStatusCode, parsedBody) or null on a transport-level failure (no internet, timeout, etc). */
    private fun request(method: String, path: String, body: JSONObject?, token: String?): Pair<Int, JSONObject?>? {
        var conn: HttpURLConnection? = null
        return try {
            conn = (URL(BASE_URL + path).openConnection() as HttpURLConnection).apply {
                requestMethod = method
                connectTimeout = 10000
                readTimeout = 10000
                setRequestProperty("Accept", "application/json")
                setRequestProperty("User-Agent", "FreeFCC-App")
                if (token != null) {
                    setRequestProperty("Authorization", "Bearer $token")
                }
                if (body != null) {
                    setRequestProperty("Content-Type", "application/json")
                    doOutput = true
                }
            }

            if (body != null) {
                conn.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
            }

            val code = conn.responseCode
            val stream = if (code in 200..299) conn.inputStream else conn.errorStream
            val text = stream?.bufferedReader()?.use { it.readText() }
            val parsed = text?.takeIf { it.isNotBlank() }?.let {
                try { JSONObject(it) } catch (_: Exception) { null }
            }

            code to parsed
        } catch (_: Exception) {
            null
        } finally {
            conn?.disconnect()
        }
    }
}
