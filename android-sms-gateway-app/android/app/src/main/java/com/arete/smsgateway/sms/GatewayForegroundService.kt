package com.arete.smsgateway.sms

import android.annotation.SuppressLint
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.Handler
import android.os.IBinder
import android.os.Looper
import android.os.PowerManager
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.ServiceCompat
import androidx.core.content.ContextCompat
import com.arete.smsgateway.MainActivity
import com.arete.smsgateway.R
import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.WritableMap

/**
 * Keeps the gateway alive while it is switched on, independently of the UI.
 *
 * Every `intervalSeconds` it runs the JS "GatewayTick" headless task (heartbeat, ack recovery,
 * queue fetch, sequential sending). The next tick is scheduled only after the previous one has
 * finished, so cycles never overlap. A partial wake lock keeps the timer honest with the screen
 * off; gateway phones are expected to stay on a charger.
 */
class GatewayForegroundService : Service() {

  private val handler = Handler(Looper.getMainLooper())
  private lateinit var prefs: GatewayPrefs
  private var wakeLock: PowerManager.WakeLock? = null
  private var tickInFlight = false
  private var tickStartedAt = 0L
  private val tickRunnable = Runnable { tick() }

  override fun onBind(intent: Intent?): IBinder? = null

  override fun onCreate() {
    super.onCreate()
    prefs = GatewayPrefs(this)
    ensureChannel(this)
  }

  override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
    when (intent?.action) {
      ACTION_STOP -> {
        shutdown("Stopped from notification")
        return START_NOT_STICKY
      }
    }
    // A null intent is a system restart after the process was killed (START_STICKY).
    if (intent == null && !prefs.desiredRunning) {
      stopSelf()
      return START_NOT_STICKY
    }
    if (!enterForeground()) {
      stopSelf()
      return START_NOT_STICKY
    }
    instance = this
    prefs.desiredRunning = true
    acquireWakeLock()
    emitState(if (intent == null) "Restarted by Android" else "Started")
    scheduleTick(0)
    return START_STICKY
  }

  override fun onDestroy() {
    handler.removeCallbacksAndMessages(null)
    releaseWakeLock()
    if (instance === this) instance = null
    emitState(prefs.lastReason.ifEmpty { "Stopped" })
    super.onDestroy()
  }

  private fun enterForeground(): Boolean =
      try {
        val type = if (Build.VERSION.SDK_INT >= 34) ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE else 0
        ServiceCompat.startForeground(this, NOTIFICATION_ID, buildNotification(), type)
        true
      } catch (e: Exception) {
        // e.g. ForegroundServiceStartNotAllowedException when started from the background.
        Log.w(TAG, "startForeground refused: ${e.javaClass.simpleName}")
        prefs.lastReason = "Android did not allow the gateway to start in the background"
        false
      }

  private fun scheduleTick(delayMs: Long) {
    handler.removeCallbacks(tickRunnable)
    handler.postDelayed(tickRunnable, delayMs)
  }

  private fun tick() {
    if (instance !== this) return
    val now = System.currentTimeMillis()
    if (tickInFlight) {
      // Watchdog: the JS task has its own timeout; this only covers a lost finish callback.
      if (now - tickStartedAt < TASK_TIMEOUT_MS + 60_000) {
        scheduleTick(15_000)
        return
      }
      Log.w(TAG, "Tick watchdog fired; starting a new cycle")
    }
    tickInFlight = true
    tickStartedAt = now
    acquireWakeLock()
    HeadlessTaskRunner.run(this, TASK_TICK, TASK_TIMEOUT_MS) { started ->
      handler.post {
        tickInFlight = false
        prefs.lastTickAt = System.currentTimeMillis()
        if (instance === this) {
          val delay = if (started) prefs.intervalSeconds * 1000L else RETRY_START_MS
          scheduleTick(delay)
        }
      }
    }
  }

  fun refreshNotification() {
    val manager = getSystemService(NotificationManager::class.java) ?: return
    runCatching { manager.notify(NOTIFICATION_ID, buildNotification()) }
  }

  private fun shutdown(reason: String) {
    prefs.desiredRunning = false
    prefs.lastReason = reason
    handler.removeCallbacksAndMessages(null)
    ServiceCompat.stopForeground(this, ServiceCompat.STOP_FOREGROUND_REMOVE)
    stopSelf()
  }

  private fun buildNotification(): Notification {
    val open =
        PendingIntent.getActivity(
            this,
            0,
            Intent(this, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
    // Android requires a notification for a service that keeps running. It is kept silent and
    // minimal: no status-bar icon (minimum importance) and, on Android 13+, hidden entirely
    // because the app never asks for notification permission.
    return NotificationCompat.Builder(this, CHANNEL_ID)
        .setSmallIcon(R.drawable.ic_stat_gateway)
        .setColor(ContextCompat.getColor(this, R.color.brand))
        .setContentTitle("Arete-SMS")
        .setContentText("Running in background")
        .setOngoing(true)
        .setSilent(true)
        .setShowWhen(false)
        .setCategory(NotificationCompat.CATEGORY_SERVICE)
        .setPriority(NotificationCompat.PRIORITY_MIN)
        .setVisibility(NotificationCompat.VISIBILITY_SECRET)
        .setContentIntent(open)
        .build()
  }

  @SuppressLint("WakelockTimeout")
  private fun acquireWakeLock() {
    val lock =
        wakeLock
            ?: getSystemService(PowerManager::class.java)
                .newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "AreteSmsGateway::gateway")
                .apply { setReferenceCounted(false) }
                .also { wakeLock = it }
    // Renewed every tick; the timeout only matters if ticks stop coming.
    lock.acquire(WAKE_LOCK_MS)
  }

  private fun releaseWakeLock() {
    wakeLock?.let { if (it.isHeld) it.release() }
    wakeLock = null
  }

  companion object {
    private const val TAG = "SmsGatewayService"
    const val CHANNEL_ID = "gateway_background"
    private const val OLD_CHANNEL_ID = "gateway_status"
    const val NOTIFICATION_ID = 4101
    const val ACTION_START = "com.arete.smsgateway.action.START"
    const val ACTION_STOP = "com.arete.smsgateway.action.STOP"
    const val TASK_TICK = "GatewayTick"
    const val TASK_TIMEOUT_MS = 8 * 60_000L
    private const val RETRY_START_MS = 20_000L
    private const val WAKE_LOCK_MS = 20 * 60_000L

    @Volatile private var instance: GatewayForegroundService? = null
    @Volatile var stateSink: ((WritableMap) -> Unit)? = null

    val isRunning: Boolean
      get() = instance != null

    fun start(context: Context, intervalSeconds: Int): Boolean {
      val prefs = GatewayPrefs(context)
      prefs.intervalSeconds = intervalSeconds
      prefs.desiredRunning = true
      prefs.lastReason = ""
      return try {
        ContextCompat.startForegroundService(
            context, Intent(context, GatewayForegroundService::class.java).setAction(ACTION_START))
        true
      } catch (e: Exception) {
        Log.w(TAG, "startForegroundService failed: ${e.javaClass.simpleName}")
        prefs.lastReason = "Android did not allow the gateway to start"
        false
      }
    }

    fun stop(context: Context, reason: String) {
      val prefs = GatewayPrefs(context)
      prefs.desiredRunning = false
      prefs.lastReason = reason
      instance?.let { svc -> svc.handler.post { svc.shutdown(reason) } }
    }

    fun tickNow() {
      instance?.let { svc -> svc.handler.post { svc.scheduleTick(0) } }
    }

    fun updateNotification(context: Context, title: String, text: String) {
      val prefs = GatewayPrefs(context)
      prefs.notificationTitle = title
      prefs.notificationText = text
      instance?.refreshNotification()
    }

    fun stateMap(context: Context, reason: String): WritableMap {
      val prefs = GatewayPrefs(context)
      return Arguments.createMap().apply {
        putBoolean("running", isRunning)
        putBoolean("desiredRunning", prefs.desiredRunning)
        putBoolean("startOnBoot", prefs.startOnBoot)
        putInt("intervalSeconds", prefs.intervalSeconds)
        putDouble("lastTickAt", prefs.lastTickAt.toDouble())
        putString("reason", reason)
      }
    }

    fun ensureChannel(context: Context) {
      if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
      val manager = context.getSystemService(NotificationManager::class.java) ?: return
      manager.deleteNotificationChannel(OLD_CHANNEL_ID)
      if (manager.getNotificationChannel(CHANNEL_ID) != null) return
      manager.createNotificationChannel(
          NotificationChannel(CHANNEL_ID, "Background service", NotificationManager.IMPORTANCE_MIN).apply {
            description = "Required by Android while the gateway runs. Silent."
            setShowBadge(false)
            setSound(null, null)
            enableVibration(false)
            lockscreenVisibility = Notification.VISIBILITY_SECRET
          })
    }
  }

  private fun emitState(reason: String) {
    val sink = stateSink ?: return
    runCatching { sink(stateMap(this, reason)) }
  }
}
