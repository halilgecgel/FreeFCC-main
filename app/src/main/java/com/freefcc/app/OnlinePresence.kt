package com.freefcc.app

import android.content.Context
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * Process-scoped online heartbeat. Survives Activity onStop so the member
 * stays online while the app is in the background (e.g. DJI Fly + FCC keepalive).
 * Stops only on logout or when the process dies.
 */
object OnlinePresence {

    private const val INTERVAL_MS = 60_000L

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private var job: Job? = null

    fun start(context: Context) {
        val app = context.applicationContext
        if (job?.isActive == true) return
        job = scope.launch {
            while (true) {
                val token = AuthManager.getToken(app) ?: break
                AuthApi.heartbeat(token)
                delay(INTERVAL_MS)
            }
        }
    }

    fun stop() {
        job?.cancel()
        job = null
    }
}
