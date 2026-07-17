package com.freefcc.app

import android.content.Context
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import java.util.concurrent.TimeUnit

class NotificationWorker(
    private val appContext: Context,
    params: WorkerParameters
) : CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result {
        val token = AuthManager.getToken(appContext) ?: return Result.success()
        val newNotifs = NotificationPoller.fetchAndMarkDelivered(appContext, token)

        for (notif in newNotifs) {
            NotificationHelper.showNotification(appContext, notif)
        }

        return Result.success()
    }

    companion object {
        private const val WORK_NAME = "freefcc_notification_poll"

        fun schedule(context: Context) {
            val request = PeriodicWorkRequestBuilder<NotificationWorker>(
                15, TimeUnit.MINUTES
            ).build()

            WorkManager.getInstance(context).enqueueUniquePeriodicWork(
                WORK_NAME,
                ExistingPeriodicWorkPolicy.KEEP,
                request
            )
        }
    }
}
