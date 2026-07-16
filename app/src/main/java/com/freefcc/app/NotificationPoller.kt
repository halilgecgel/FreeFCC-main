package com.freefcc.app

import android.content.Context
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

data class AppNotification(
    val id: Long,
    val title: String,
    val message: String,
    val type: String,
    val createdAt: String
)

object NotificationPoller {

    private const val PREF_KEY = "last_seen_notification_id"

    fun getLastSeenId(context: Context): Long {
        return context.getSharedPreferences("freefcc", Context.MODE_PRIVATE)
            .getLong(PREF_KEY, 0)
    }

    fun saveLastSeenId(context: Context, id: Long) {
        context.getSharedPreferences("freefcc", Context.MODE_PRIVATE)
            .edit().putLong(PREF_KEY, id).apply()
    }

    /**
     * Fetches new notifications from the server since the given ID.
     * Requires a valid auth token.
     */
    fun fetchNew(token: String, afterId: Long): List<AppNotification> {
        var conn: HttpURLConnection? = null
        return try {
            val url = "${AuthApi.BASE_URL}/notifications?after_id=$afterId"
            conn = (URL(url).openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                connectTimeout = 8000
                readTimeout = 8000
                setRequestProperty("Accept", "application/json")
                setRequestProperty("User-Agent", "FreeFCC-App")
                setRequestProperty("Authorization", "Bearer $token")
            }

            if (conn.responseCode != 200) return emptyList()

            val body = conn.inputStream.bufferedReader().use { it.readText() }
            val json = JSONObject(body)
            val arr = json.optJSONArray("notifications") ?: return emptyList()

            val result = mutableListOf<AppNotification>()
            for (i in 0 until arr.length()) {
                val obj = arr.getJSONObject(i)
                result.add(
                    AppNotification(
                        id = obj.optLong("id", 0),
                        title = obj.optString("title", ""),
                        message = obj.optString("message", ""),
                        type = obj.optString("type", "info"),
                        createdAt = obj.optString("created_at", "")
                    )
                )
            }
            result
        } catch (_: Exception) {
            emptyList()
        } finally {
            conn?.disconnect()
        }
    }
}
