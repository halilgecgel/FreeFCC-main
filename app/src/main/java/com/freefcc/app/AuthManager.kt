package com.freefcc.app

import android.annotation.SuppressLint
import android.content.Context
import android.provider.Settings
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey

/**
 * Persists the current member's session (bearer token + basic profile info)
 * in an encrypted SharedPreferences file, and resolves the device identity
 * used for the backend's one-account-one-device lock.
 */
object AuthManager {

    private const val PREFS_NAME = "freefcc_auth"
    private const val KEY_TOKEN = "token"
    private const val KEY_USERNAME = "username"
    private const val KEY_NAME = "name"
    private const val KEY_EXPIRES_AT = "expires_at"

    private fun prefs(context: Context) = EncryptedSharedPreferences.create(
        context,
        PREFS_NAME,
        MasterKey.Builder(context).setKeyScheme(MasterKey.KeyScheme.AES256_GCM).build(),
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
    )

    fun getToken(context: Context): String? =
        prefs(context).getString(KEY_TOKEN, null)

    fun saveSession(context: Context, token: String, member: MemberInfo) {
        prefs(context).edit()
            .putString(KEY_TOKEN, token)
            .putString(KEY_USERNAME, member.username)
            .putString(KEY_NAME, member.name)
            .putString(KEY_EXPIRES_AT, member.expiresAt)
            .apply()
    }

    fun getCachedMember(context: Context): MemberInfo? {
        val p = prefs(context)
        val username = p.getString(KEY_USERNAME, null) ?: return null
        return MemberInfo(
            username = username,
            name = p.getString(KEY_NAME, null),
            expiresAt = p.getString(KEY_EXPIRES_AT, null)
        )
    }

    fun clearSession(context: Context) {
        prefs(context).edit().clear().apply()
    }

    /**
     * A stable per-device identifier used to lock a member's account to one
     * device. Survives app reinstalls (unlike a self-generated UUID) but
     * changes on factory reset — acceptable since an admin can reset the
     * device lock from the panel if that ever happens.
     */
    @SuppressLint("HardwareIds")
    fun getDeviceId(context: Context): String {
        val id = Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID)
        return if (id.isNullOrBlank()) "unknown-device" else id
    }
}
