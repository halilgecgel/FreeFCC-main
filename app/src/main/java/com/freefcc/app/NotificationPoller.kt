package com.freefcc.app

import android.content.Context
import org.json.JSONArray
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
    private val pollLock = Any()

    fun getLastSeenId(context: Context): Long {
        return context.getSharedPreferences("freefcc", Context.MODE_PRIVATE)
            .getLong(PREF_KEY, 0)
    }

    fun saveLastSeenId(context: Context, id: Long) {
        context.getSharedPreferences("freefcc", Context.MODE_PRIVATE)
            .edit().putLong(PREF_KEY, id).apply()
    }

    /**
     * Atomically fetches undelivered notifications and marks them delivered on the server.
     * Prevents ViewModel + Worker race from showing the same notification twice.
     * If the ack fails, returns empty so the next poll can retry without duplicates.
     */
    fun fetchAndMarkDelivered(context: Context, token: String): List<AppNotification> {
        synchronized(pollLock) {
            val afterId = getLastSeenId(context)
            val newNotifs = fetchNew(token, afterId)
            if (newNotifs.isEmpty()) return emptyList()

            val ids = newNotifs.map { it.id }
            if (!ackDelivered(token, ids)) return emptyList()

            saveLastSeenId(context, ids.maxOrNull() ?: afterId)
            return newNotifs
        }
    }

    /**
     * Fetches new notifications from the server since the given ID.
     * Server also excludes already-delivered receipts for this member.
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

    fun ackDelivered(token: String, ids: List<Long>): Boolean {
        return postIds("${AuthApi.BASE_URL}/notifications/delivered", token, ids)
    }

    fun ackRead(token: String, ids: List<Long>): Boolean {
        return postIds("${AuthApi.BASE_URL}/notifications/read", token, ids)
    }

    private fun postIds(url: String, token: String, ids: List<Long>): Boolean {
        if (ids.isEmpty()) return true
        var conn: HttpURLConnection? = null
        return try {
            val body = JSONObject().put("ids", JSONArray(ids)).toString()
            conn = (URL(url).openConnection() as HttpURLConnection).apply {
                requestMethod = "POST"
                doOutput = true
                connectTimeout = 8000
                readTimeout = 8000
                setRequestProperty("Content-Type", "application/json")
                setRequestProperty("Accept", "application/json")
                setRequestProperty("User-Agent", "FreeFCC-App")
                setRequestProperty("Authorization", "Bearer $token")
            }
            conn.outputStream.use { it.write(body.toByteArray(Charsets.UTF_8)) }
            conn.responseCode in 200..299
        } catch (_: Exception) {
            false
        } finally {
            conn?.disconnect()
        }
    }
}
