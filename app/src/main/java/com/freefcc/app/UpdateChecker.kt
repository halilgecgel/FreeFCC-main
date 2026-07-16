package com.freefcc.app

import android.content.Context
import org.json.JSONObject
import java.io.File
import java.io.FileOutputStream
import java.net.HttpURLConnection
import java.net.URL
import java.security.MessageDigest

data class UpdateInfo(
    val version: String,
    val versionCode: Int,
    val title: String,
    val changelog: String,
    val downloadUrl: String,
    val apkSize: Long,
    val publishedAt: String,
    val sha256: String?,
    val isForced: Boolean,
    val forceAfter: String?
) {
    fun isNewerThan(currentVersionCode: Int): Boolean {
        return versionCode > currentVersionCode
    }
}

sealed class UpdateCheckResult {
    data class Available(val info: UpdateInfo) : UpdateCheckResult()
    data object UpToDate : UpdateCheckResult()
    data object Failed : UpdateCheckResult()
}

object UpdateChecker {

    fun fetchLatestResult(currentVersion: String, currentVersionCode: Int): UpdateCheckResult {
        var conn: HttpURLConnection? = null
        return try {
            val url = "${AuthApi.BASE_URL}/check-update?current_version=$currentVersion&version_code=$currentVersionCode"
            conn = (URL(url).openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                connectTimeout = 8000
                readTimeout = 8000
                setRequestProperty("Accept", "application/json")
                setRequestProperty("User-Agent", "FreeFCC-App")
            }

            if (conn.responseCode != 200) return UpdateCheckResult.Failed

            val body = conn.inputStream.bufferedReader().use { it.readText() }
            val json = JSONObject(body)

            if (!json.optBoolean("has_update", false)) return UpdateCheckResult.UpToDate

            UpdateCheckResult.Available(
                UpdateInfo(
                    version = json.optString("version", ""),
                    versionCode = json.optInt("version_code", 0),
                    title = json.optString("title", ""),
                    changelog = json.optString("changelog", "").trim(),
                    downloadUrl = json.optString("download_url", ""),
                    apkSize = json.optLong("apk_size", 0),
                    publishedAt = json.optString("published_at", ""),
                    sha256 = json.optString("sha256", "").ifEmpty { null },
                    isForced = json.optBoolean("is_forced", false),
                    forceAfter = json.optString("force_after", "").ifEmpty { null }
                )
            )
        } catch (_: Exception) {
            UpdateCheckResult.Failed
        } finally {
            conn?.disconnect()
        }
    }

    sealed class DownloadResult {
        data class Ok(val file: File) : DownloadResult()
        data class Err(val message: String) : DownloadResult()
    }

    /**
     * Downloads the APK file to the app cache directory.
     * Calls onProgress with bytes downloaded / total bytes.
     * Verifies the SHA-256 digest if the server provided one.
     */
    fun downloadApk(context: Context, info: UpdateInfo, onProgress: (Float) -> Unit): DownloadResult {
        if (info.downloadUrl.isBlank()) {
            return DownloadResult.Err("İndirme adresi boş")
        }

        // One retry helps when the LAN / artisan server briefly drops the stream.
        var lastError = "İndirme başarısız"
        repeat(2) { attempt ->
            when (val result = downloadApkOnce(context, info, onProgress)) {
                is DownloadResult.Ok -> return result
                is DownloadResult.Err -> {
                    lastError = result.message
                    if (attempt == 0) Thread.sleep(800)
                }
            }
        }
        return DownloadResult.Err(lastError)
    }

    private fun downloadApkOnce(
        context: Context,
        info: UpdateInfo,
        onProgress: (Float) -> Unit
    ): DownloadResult {
        var conn: HttpURLConnection? = null
        val outputDir = File(context.cacheDir, "updates").apply { mkdirs() }
        val outputFile = File(outputDir, "freefcc_update.apk")
        return try {
            if (outputFile.exists()) outputFile.delete()

            conn = (URL(info.downloadUrl).openConnection() as HttpURLConnection).apply {
                requestMethod = "GET"
                instanceFollowRedirects = true
                // 20MB+ APK over controller Wi‑Fi can take several minutes.
                connectTimeout = 30_000
                readTimeout = 300_000
                setRequestProperty("User-Agent", "FreeFCC-App")
                setRequestProperty("Accept-Encoding", "identity")
            }

            val code = conn.responseCode
            if (code !in 200..299) {
                return DownloadResult.Err("Sunucu yanıtı: HTTP $code")
            }

            val reported = conn.contentLengthLong
            val totalBytes = when {
                reported > 0L -> reported
                info.apkSize > 0L -> info.apkSize
                else -> 1L
            }
            val md = info.sha256?.let { MessageDigest.getInstance("SHA-256") }

            FileOutputStream(outputFile).use { fos ->
                conn.inputStream.use { input ->
                    val buffer = ByteArray(64 * 1024)
                    var downloaded = 0L
                    while (true) {
                        val read = input.read(buffer)
                        if (read <= 0) break
                        fos.write(buffer, 0, read)
                        md?.update(buffer, 0, read)
                        downloaded += read
                        onProgress((downloaded.toFloat() / totalBytes).coerceIn(0f, 1f))
                    }
                }
            }

            if (!outputFile.exists() || outputFile.length() == 0L) {
                outputFile.delete()
                return DownloadResult.Err("İndirilen dosya boş")
            }

            if (info.apkSize > 0L && outputFile.length() != info.apkSize) {
                // Soft check: still accept if sha256 matches; otherwise fail.
                if (info.sha256.isNullOrBlank()) {
                    outputFile.delete()
                    return DownloadResult.Err(
                        "Dosya boyutu uyuşmuyor (${outputFile.length()}/${info.apkSize})"
                    )
                }
            }

            if (md != null) {
                val actual = md.digest().joinToString("") { "%02x".format(it) }
                if (!actual.equals(info.sha256, ignoreCase = true)) {
                    outputFile.delete()
                    return DownloadResult.Err("Dosya bütünlüğü doğrulanamadı")
                }
            }

            onProgress(1f)
            DownloadResult.Ok(outputFile)
        } catch (e: Exception) {
            outputFile.delete()
            DownloadResult.Err(e.message?.takeIf { it.isNotBlank() } ?: e.javaClass.simpleName)
        } finally {
            conn?.disconnect()
        }
    }
}
