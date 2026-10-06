package com.arete.smsgateway.sms

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/**
 * Restarts the gateway after a reboot or app update, but only when the admin enabled
 * "Start after reboot" AND the gateway was running. A gateway the user stopped stays stopped.
 */
class BootReceiver : BroadcastReceiver() {
  override fun onReceive(context: Context, intent: Intent) {
    val action = intent.action
    if (action != Intent.ACTION_BOOT_COMPLETED && action != Intent.ACTION_MY_PACKAGE_REPLACED) return
    val prefs = GatewayPrefs(context)
    if (prefs.desiredRunning && (prefs.startOnBoot || action == Intent.ACTION_MY_PACKAGE_REPLACED)) {
      GatewayForegroundService.start(context, prefs.intervalSeconds)
    }
    MaintenanceWorker.schedule(context)
  }
}
