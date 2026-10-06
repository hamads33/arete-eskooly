package com.arete.smsgateway.sms

import android.content.Context
import android.content.SharedPreferences

/** Native-side gateway run state, readable without the JS runtime (boot, service restarts). */
class GatewayPrefs(context: Context) {
  private val prefs: SharedPreferences =
      context.applicationContext.getSharedPreferences("sms_gateway_service", Context.MODE_PRIVATE)

  var desiredRunning: Boolean
    get() = prefs.getBoolean("desired_running", false)
    set(v) {
      prefs.edit().putBoolean("desired_running", v).apply()
    }

  var startOnBoot: Boolean
    get() = prefs.getBoolean("start_on_boot", false)
    set(v) {
      prefs.edit().putBoolean("start_on_boot", v).apply()
    }

  var intervalSeconds: Int
    get() = prefs.getInt("interval_seconds", DEFAULT_INTERVAL).coerceIn(MIN_INTERVAL, MAX_INTERVAL)
    set(v) {
      prefs.edit().putInt("interval_seconds", v.coerceIn(MIN_INTERVAL, MAX_INTERVAL)).apply()
    }

  var lastTickAt: Long
    get() = prefs.getLong("last_tick_at", 0)
    set(v) {
      prefs.edit().putLong("last_tick_at", v).apply()
    }

  var notificationTitle: String
    get() = prefs.getString("notification_title", null) ?: "SMS Gateway Active"
    set(v) {
      prefs.edit().putString("notification_title", v).apply()
    }

  var notificationText: String
    get() = prefs.getString("notification_text", null) ?: "Starting…"
    set(v) {
      prefs.edit().putString("notification_text", v).apply()
    }

  var lastReason: String
    get() = prefs.getString("last_reason", null) ?: ""
    set(v) {
      prefs.edit().putString("last_reason", v).apply()
    }

  companion object {
    const val DEFAULT_INTERVAL = 30
    const val MIN_INTERVAL = 10
    const val MAX_INTERVAL = 600
  }
}
