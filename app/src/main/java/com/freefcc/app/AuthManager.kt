package com.freefcc.app

import android.annotation.SuppressLint
import android.content.Context
import android.content.SharedPreferences
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
    private const val KEY_DEVICE_MODEL_ID = "device_model_id"
    private const val KEY_DEVICE_MODEL_NAME = "device_model_name"
    private const val KEY_DEVICE_MODEL_SLUG = "device_model_slug"

    @Volatile
    private var cachedPrefs: SharedPreferences? = null
    private val prefsLock = Any()

    /** Creating EncryptedSharedPreferences + MasterKey is expensive — cache one instance. */
    private fun prefs(context: Context): SharedPreferences {
        cachedPrefs?.let { return it }
        synchronized(prefsLock) {
            cachedPrefs?.let { return it }
            val created = EncryptedSharedPreferences.create(
                context.applicationContext,
                PREFS_NAME,
                MasterKey.Builder(context.applicationContext)
                    .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
                    .build(),
                EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
            )
            cachedPrefs = created
            return created
        }
    }

    fun getToken(context: Context): String? =
        prefs(context).getString(KEY_TOKEN, null)

    fun saveSession(context: Context, token: String, member: MemberInfo) {
        prefs(context).edit()
            .putString(KEY_TOKEN, token)
            .putString(KEY_USERNAME, member.username)
            .putString(KEY_NAME, member.name)
            .putString(KEY_EXPIRES_AT, member.expiresAt)
            .apply()
        saveMemberProfile(context, member)
    }

    fun saveMemberProfile(context: Context, member: MemberInfo) {
        val editor = prefs(context).edit()
            .putString(KEY_USERNAME, member.username)
            .putString(KEY_NAME, member.name)
            .putString(KEY_EXPIRES_AT, member.expiresAt)

        val model = member.deviceModel
        if (model != null) {
            editor
                .putLong(KEY_DEVICE_MODEL_ID, model.id)
                .putString(KEY_DEVICE_MODEL_NAME, model.name)
                .putString(KEY_DEVICE_MODEL_SLUG, model.slug)
        } else {
            editor
                .remove(KEY_DEVICE_MODEL_ID)
                .remove(KEY_DEVICE_MODEL_NAME)
                .remove(KEY_DEVICE_MODEL_SLUG)
        }

        editor.apply()
    }

    fun getCachedMember(context: Context): MemberInfo? {
        val p = prefs(context)
        val username = p.getString(KEY_USERNAME, null) ?: return null
        val modelId = p.getLong(KEY_DEVICE_MODEL_ID, -1L)
        val modelName = p.getString(KEY_DEVICE_MODEL_NAME, null)
        val modelSlug = p.getString(KEY_DEVICE_MODEL_SLUG, null)
        val deviceModel = if (modelId > 0 && !modelName.isNullOrBlank() && !modelSlug.isNullOrBlank()) {
            DeviceModelInfo(id = modelId, name = modelName, slug = modelSlug)
        } else {
            null
        }
        return MemberInfo(
            username = username,
            name = p.getString(KEY_NAME, null),
            expiresAt = p.getString(KEY_EXPIRES_AT, null),
            deviceModel = deviceModel
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
