package com.arete.smsgateway.sms

import android.Manifest
import android.annotation.SuppressLint
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.PowerManager
import android.provider.Settings
import android.telephony.SubscriptionManager
import androidx.core.content.ContextCompat
import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.Promise
import com.facebook.react.bridge.ReactApplicationContext
import com.facebook.react.bridge.ReadableMap
import com.facebook.react.bridge.WritableMap
import java.util.UUID
import java.util.concurrent.Executors

/**
 * TurboModule "SmsGateway" (spec: src/native/NativeSmsGateway.ts).
 *
 * Only Android-specific work lives here: SIM discovery, SmsManager, callbacks, the foreground
 * service and device metadata. Queue logic, API calls and job state are in TypeScript.
 */
class SmsGatewayModule(private val context: ReactApplicationContext) : NativeSmsGatewaySpec(context) {

  private val io = Executors.newSingleThreadExecutor()
  private val smsSink: (WritableMap) -> Unit = { emitOnSmsEvent(it) }
  private val stateSink: (WritableMap) -> Unit = { emitOnGatewayServiceEvent(it) }

  init {
    SmsEvents.sink = smsSink
    GatewayForegroundService.stateSink = stateSink
  }

  override fun invalidate() {
    if (SmsEvents.sink === smsSink) SmsEvents.sink = null
    if (GatewayForegroundService.stateSink === stateSink) GatewayForegroundService.stateSink = null
    io.shutdown()
    super.invalidate()
  }

  // ---- SIMs & capability ------------------------------------------------------------------

  override fun getAvailableSims(promise: Promise) {
    io.execute {
      try {
        val list = Arguments.createArray()
        for (sim in SimDirectory.activeSims(context)) {
          list.pushMap(
              Arguments.createMap().apply {
                putInt("subscriptionId", sim.subscriptionId)
                putInt("slotIndex", sim.slotIndex)
                putNullableString("carrierName", sim.carrierName)
                putNullableString("displayName", sim.displayName)
                putNullableString("phoneNumber", sim.phoneNumber)
                putNullableString("countryIso", sim.countryIso)
                putBoolean("isEmbedded", sim.isEmbedded)
              })
        }
        promise.resolve(list)
      } catch (e: SecurityException) {
        promise.reject("PERMISSION_REQUIRED", "Phone state permission is required to list SIM cards.")
      } catch (e: Exception) {
        promise.reject("SIM_QUERY_FAILED", "Could not read SIM information.")
      }
    }
  }

  override fun getSmsCapabilityStatus(promise: Promise) {
    io.execute {
      val pm = context.packageManager
      promise.resolve(
          Arguments.createMap().apply {
            putBoolean("hasTelephony", pm.hasSystemFeature(PackageManager.FEATURE_TELEPHONY_MESSAGING))
            putBoolean("sendSmsPermission", granted(Manifest.permission.SEND_SMS))
            putBoolean("phoneStatePermission", granted(Manifest.permission.READ_PHONE_STATE))
            putBoolean(
                "notificationPermission",
                Build.VERSION.SDK_INT < 33 || granted(Manifest.permission.POST_NOTIFICATIONS))
            putInt("activeSimCount", SimDirectory.activeSimCount(context))
            putInt(
                "defaultSmsSubscriptionId",
                SimDirectory.defaultSmsSubscriptionId().takeIf { it != SubscriptionManager.INVALID_SUBSCRIPTION_ID } ?: -1)
            putBoolean("airplaneMode", DeviceStatus.isAirplaneModeOn(context))
          })
    }
  }

  // ---- Sending ----------------------------------------------------------------------------

  override fun sendSms(request: ReadableMap, promise: Promise) {
    val jobId = request.getString("jobId").orEmpty()
    val phone = request.getString("phoneNumber").orEmpty()
    val message = request.getString("message").orEmpty()
    val subscriptionId = if (request.hasKey("subscriptionId")) request.getDouble("subscriptionId").toInt() else -1
    val delivery = request.hasKey("requestDeliveryReport") && request.getBoolean("requestDeliveryReport")
    io.execute {
      try {
        val accepted = SmsSender.send(context, jobId, phone, message, subscriptionId, delivery)
        promise.resolve(
            Arguments.createMap().apply {
              putString("jobId", accepted.jobId)
              putString("sendToken", accepted.sendToken)
              putInt("segmentCount", accepted.segmentCount)
            })
      } catch (e: SmsSendException) {
        promise.reject(e.code, e.message)
      } catch (e: Exception) {
        promise.reject(SmsErrorMapper.SEND_EXCEPTION, "Unexpected error while handing the SMS to Android.")
      }
    }
  }

  override fun getStoredResults(promise: Promise) {
    io.execute {
      val list = Arguments.createArray()
      for (r in SmsResultStore.get(context).all()) {
        list.pushMap(
            Arguments.createMap().apply {
              putString("jobId", r.jobId)
              putString("sendToken", r.sendToken)
              putString("status", r.status)
              putNullableString("errorCode", r.errorCode)
              putInt("resultCode", r.resultCode)
              putInt("segmentCount", r.segmentCount)
              putInt("segmentsSent", r.segmentsDone.size)
              putDouble("startedAt", r.startedAt.toDouble())
              putDouble("completedAt", r.completedAt.toDouble())
              putDouble("deliveredAt", r.deliveredAt.toDouble())
            })
      }
      promise.resolve(list)
    }
  }

  override fun clearStoredResult(jobId: String) {
    io.execute { SmsResultStore.get(context).clear(jobId) }
  }

  // ---- Device -----------------------------------------------------------------------------

  override fun getDeviceSnapshot(promise: Promise) {
    io.execute {
      val (versionName, versionCode) = DeviceStatus.appVersion(context)
      promise.resolve(
          Arguments.createMap().apply {
            putInt("batteryLevel", DeviceStatus.batteryLevel(context))
            putBoolean("charging", DeviceStatus.isCharging(context))
            putString("networkType", DeviceStatus.networkType(context))
            putString("androidVersion", Build.VERSION.RELEASE ?: "")
            putInt("sdkInt", Build.VERSION.SDK_INT)
            putString("manufacturer", Build.MANUFACTURER ?: "")
            putString("model", Build.MODEL ?: "")
            putString("appVersion", versionName)
            putInt("appBuild", versionCode.toInt())
            putBoolean("debugBuild", com.arete.smsgateway.BuildConfig.DEBUG)
          })
    }
  }

  override fun generateUuid(): String = UUID.randomUUID().toString()

  // ---- Gateway service --------------------------------------------------------------------

  override fun startGateway(intervalSeconds: Double, promise: Promise) {
    promise.resolve(GatewayForegroundService.start(context, intervalSeconds.toInt()))
  }

  override fun stopGateway(promise: Promise) {
    GatewayForegroundService.stop(context, "Stopped by user")
    promise.resolve(true)
  }

  override fun getGatewayServiceState(promise: Promise) {
    promise.resolve(GatewayForegroundService.stateMap(context, GatewayPrefs(context).lastReason))
  }

  override fun updateGatewayNotification(title: String, text: String) {
    GatewayForegroundService.updateNotification(context, title, text)
  }

  override fun setGatewayInterval(seconds: Double) {
    GatewayPrefs(context).intervalSeconds = seconds.toInt()
  }

  override fun setStartOnBoot(enabled: Boolean) {
    GatewayPrefs(context).startOnBoot = enabled
  }

  override fun scheduleMaintenance() {
    MaintenanceWorker.schedule(context)
  }

  override fun cancelMaintenance() {
    MaintenanceWorker.cancel(context)
  }

  // ---- System settings --------------------------------------------------------------------

  override fun isIgnoringBatteryOptimizations(promise: Promise) {
    val pm = context.getSystemService(PowerManager::class.java)
    promise.resolve(pm?.isIgnoringBatteryOptimizations(context.packageName) == true)
  }

  @SuppressLint("BatteryLife")
  override fun requestIgnoreBatteryOptimizations() {
    val intent =
        Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS)
            .setData(Uri.parse("package:${context.packageName}"))
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    runCatching { context.startActivity(intent) }
        .onFailure {
          runCatching {
            context.startActivity(
                Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
          }
        }
  }

  override fun openAppSettings() {
    runCatching {
      context.startActivity(
          Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS)
              .setData(Uri.parse("package:${context.packageName}"))
              .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
    }
  }

  private fun granted(permission: String) =
      ContextCompat.checkSelfPermission(context, permission) == PackageManager.PERMISSION_GRANTED

  private fun WritableMap.putNullableString(key: String, value: String?) {
    if (value == null) putNull(key) else putString(key, value)
  }
}
