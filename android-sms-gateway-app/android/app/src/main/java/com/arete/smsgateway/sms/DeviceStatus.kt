package com.arete.smsgateway.sms

import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.BatteryManager
import android.os.Build
import android.provider.Settings

/** Best-effort heartbeat metadata. Every field degrades to a neutral value instead of throwing. */
object DeviceStatus {

  fun batteryLevel(context: Context): Int =
      runCatching {
            val bm = context.getSystemService(BatteryManager::class.java)
            bm.getIntProperty(BatteryManager.BATTERY_PROPERTY_CAPACITY)
          }
          .getOrDefault(-1)
          .let { if (it in 0..100) it else -1 }

  fun isCharging(context: Context): Boolean =
      runCatching {
            val intent = context.registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED))
            val status = intent?.getIntExtra(BatteryManager.EXTRA_STATUS, -1) ?: -1
            status == BatteryManager.BATTERY_STATUS_CHARGING || status == BatteryManager.BATTERY_STATUS_FULL
          }
          .getOrDefault(false)

  fun networkType(context: Context): String =
      runCatching {
            val cm = context.getSystemService(ConnectivityManager::class.java)
            val caps = cm.getNetworkCapabilities(cm.activeNetwork) ?: return "none"
            when {
              caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) -> "wifi"
              caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR) -> "mobile"
              caps.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET) -> "ethernet"
              caps.hasTransport(NetworkCapabilities.TRANSPORT_VPN) -> "vpn"
              else -> "other"
            }
          }
          .getOrDefault("unknown")

  fun isAirplaneModeOn(context: Context): Boolean =
      Settings.Global.getInt(context.contentResolver, Settings.Global.AIRPLANE_MODE_ON, 0) != 0

  fun appVersion(context: Context): Pair<String, Long> =
      runCatching {
            val info = context.packageManager.getPackageInfo(context.packageName, 0)
            val code =
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) info.longVersionCode
                else @Suppress("DEPRECATION") info.versionCode.toLong()
            (info.versionName ?: "0") to code
          }
          .getOrDefault("0" to 0L)
}
