package com.arete.smsgateway.sms

import android.content.Context
import android.util.Log
import com.facebook.react.ReactApplication
import com.facebook.react.ReactInstanceEventListener
import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.ReactContext
import com.facebook.react.bridge.UiThreadUtil
import com.facebook.react.jstasks.HeadlessJsTaskConfig
import com.facebook.react.jstasks.HeadlessJsTaskContext
import com.facebook.react.jstasks.HeadlessJsTaskEventListener

/**
 * Runs one registered JS headless task (see index.js) and reports when it has finished, timed
 * out, or could not start. Starts the React runtime if the UI is not open. The JS side owns the
 * gateway logic; native code only decides *when* it runs, so it keeps running with the screen off.
 */
object HeadlessTaskRunner {
  private const val TAG = "SmsGatewayHeadless"

  fun run(context: Context, taskKey: String, timeoutMs: Long, onDone: (Boolean) -> Unit) {
    UiThreadUtil.runOnUiThread {
      try {
        val host = (context.applicationContext as ReactApplication).reactHost
        if (host == null) {
          onDone(false)
          return@runOnUiThread
        }
        val current = host.currentReactContext
        if (current != null) {
          start(current, taskKey, timeoutMs, onDone)
        } else {
          host.addReactInstanceEventListener(
              object : ReactInstanceEventListener {
                override fun onReactContextInitialized(context: ReactContext) {
                  host.removeReactInstanceEventListener(this)
                  start(context, taskKey, timeoutMs, onDone)
                }
              })
          host.start()
        }
      } catch (e: Exception) {
        Log.w(TAG, "Could not start $taskKey: ${e.javaClass.simpleName}")
        onDone(false)
      }
    }
  }

  private fun start(reactContext: ReactContext, taskKey: String, timeoutMs: Long, onDone: (Boolean) -> Unit) {
    val tasks = HeadlessJsTaskContext.getInstance(reactContext)
    var taskId = -1
    val listener =
        object : HeadlessJsTaskEventListener {
          override fun onHeadlessJsTaskStart(taskId: Int) = Unit

          override fun onHeadlessJsTaskFinish(finishedId: Int) {
            if (finishedId != taskId) return
            tasks.removeTaskEventListener(this)
            onDone(true)
          }
        }
    tasks.addTaskEventListener(listener)
    try {
      // Finish callbacks are posted to the UI thread, which we are on, so taskId is set first.
      taskId = tasks.startTask(HeadlessJsTaskConfig(taskKey, Arguments.createMap(), timeoutMs, true))
    } catch (e: Exception) {
      tasks.removeTaskEventListener(listener)
      Log.w(TAG, "startTask $taskKey failed: ${e.javaClass.simpleName}")
      onDone(false)
    }
  }
}
