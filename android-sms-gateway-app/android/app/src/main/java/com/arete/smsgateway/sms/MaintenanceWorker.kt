package com.arete.smsgateway.sms

import android.content.Context
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.Worker
import androidx.work.WorkerParameters
import java.util.concurrent.CountDownLatch
import java.util.concurrent.TimeUnit

/**
 * Every ~15 minutes (WorkManager's minimum) while the gateway is stopped, delivers pending
 * sent/failed acknowledgments to the LMS. It never sends SMS. When the foreground service is
 * running it does nothing, because the service's own cycle already covers acknowledgments.
 */
class MaintenanceWorker(context: Context, params: WorkerParameters) : Worker(context, params) {
  override fun doWork(): Result {
    if (GatewayForegroundService.isRunning) return Result.success()
    val latch = CountDownLatch(1)
    HeadlessTaskRunner.run(applicationContext, TASK_KEY, TASK_TIMEOUT_MS) { latch.countDown() }
    latch.await(TASK_TIMEOUT_MS + 30_000, TimeUnit.MILLISECONDS)
    return Result.success()
  }

  companion object {
    const val TASK_KEY = "GatewayMaintenance"
    private const val UNIQUE_NAME = "sms-gateway-maintenance"
    private const val TASK_TIMEOUT_MS = 3 * 60_000L

    fun schedule(context: Context) {
      val request =
          PeriodicWorkRequestBuilder<MaintenanceWorker>(15, TimeUnit.MINUTES)
              .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
              .build()
      WorkManager.getInstance(context)
          .enqueueUniquePeriodicWork(UNIQUE_NAME, ExistingPeriodicWorkPolicy.KEEP, request)
    }

    fun cancel(context: Context) {
      WorkManager.getInstance(context).cancelUniqueWork(UNIQUE_NAME)
    }
  }
}
