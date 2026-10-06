package com.arete.smsgateway.sms

import android.Manifest
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import androidx.core.content.ContextCompat
import java.util.UUID

class SmsSendException(val code: String, message: String) : Exception(message)

/**
 * Hands one logical message to SmsManager. Success is NOT decided here: the outcome arrives later
 * through [SmsSentReceiver], one broadcast per segment, and is aggregated in [SmsResultStore].
 */
object SmsSender {
  const val ACTION_SENT = "com.arete.smsgateway.SMS_SENT"
  const val ACTION_DELIVERED = "com.arete.smsgateway.SMS_DELIVERED"
  const val EXTRA_JOB_ID = "jobId"
  const val EXTRA_SEND_TOKEN = "sendToken"
  const val EXTRA_SEGMENT_INDEX = "segmentIndex"
  const val EXTRA_SEGMENT_COUNT = "segmentCount"

  data class Accepted(val jobId: String, val sendToken: String, val segmentCount: Int)

  fun send(
      context: Context,
      jobId: String,
      phoneNumber: String,
      message: String,
      subscriptionId: Int,
      requestDeliveryReport: Boolean,
  ): Accepted {
    val app = context.applicationContext
    if (ContextCompat.checkSelfPermission(app, Manifest.permission.SEND_SMS) != PackageManager.PERMISSION_GRANTED) {
      throw SmsSendException(SmsErrorMapper.PERMISSION_DENIED, "SEND_SMS permission is not granted.")
    }
    if (jobId.isBlank()) throw SmsSendException(SmsErrorMapper.INVALID_MESSAGE, "jobId is required.")
    val destination = phoneNumber.trim()
    if (!destination.matches(Regex("^\\+?[0-9]{3,20}$"))) {
      throw SmsSendException(SmsErrorMapper.INVALID_NUMBER, "The phone number is not valid.")
    }
    if (message.isEmpty()) throw SmsSendException(SmsErrorMapper.INVALID_MESSAGE, "The message is empty.")
    if (subscriptionId >= 0 && SimDirectory.hasPhoneStatePermission(app) && !SimDirectory.isActive(app, subscriptionId)) {
      throw SmsSendException(SmsErrorMapper.SIM_UNAVAILABLE, "The selected SIM is no longer available.")
    }

    val manager =
        runCatching { SimDirectory.smsManagerFor(app, subscriptionId) }
            .getOrElse { throw SmsSendException(SmsErrorMapper.SIM_UNAVAILABLE, "No SMS manager for this SIM.") }
    // divideMessage picks GSM-7 or UCS-2 per the text (Urdu needs UCS-2) and never alters it.
    val parts = manager.divideMessage(message)
    if (parts.isEmpty()) throw SmsSendException(SmsErrorMapper.INVALID_MESSAGE, "The message could not be encoded.")
    val count = parts.size

    val sendToken = UUID.randomUUID().toString()
    val store = SmsResultStore.get(app)
    store.begin(jobId, sendToken, count)

    val sentIntents = ArrayList<PendingIntent>(count)
    val deliveryIntents = if (requestDeliveryReport) ArrayList<PendingIntent>(count) else null
    for (i in 0 until count) {
      sentIntents += pendingIntent(app, ACTION_SENT, SmsSentReceiver::class.java, jobId, sendToken, i, count)
      deliveryIntents?.add(pendingIntent(app, ACTION_DELIVERED, SmsDeliveredReceiver::class.java, jobId, sendToken, i, count))
    }

    try {
      if (count == 1) {
        manager.sendTextMessage(destination, null, message, sentIntents[0], deliveryIntents?.get(0))
      } else {
        manager.sendMultipartTextMessage(destination, null, parts, sentIntents, deliveryIntents)
      }
    } catch (e: SecurityException) {
      store.failImmediately(jobId, sendToken, SmsErrorMapper.PERMISSION_DENIED)
      throw SmsSendException(SmsErrorMapper.PERMISSION_DENIED, "Android refused the SMS: permission denied.")
    } catch (e: IllegalArgumentException) {
      store.failImmediately(jobId, sendToken, SmsErrorMapper.INVALID_NUMBER)
      throw SmsSendException(SmsErrorMapper.INVALID_NUMBER, "Android rejected the destination or text.")
    } catch (e: Exception) {
      store.failImmediately(jobId, sendToken, SmsErrorMapper.SEND_EXCEPTION)
      throw SmsSendException(SmsErrorMapper.SEND_EXCEPTION, "SmsManager threw ${e.javaClass.simpleName}.")
    }

    SmsEvents.emit(jobId, sendToken, "sending", segmentCount = count)
    return Accepted(jobId, sendToken, count)
  }

  /**
   * The data URI makes every intent distinct under Intent.filterEquals, so Android can never
   * hand back an older PendingIntent for another job/segment/attempt even if request codes collide.
   */
  private fun pendingIntent(
      context: Context,
      action: String,
      receiver: Class<*>,
      jobId: String,
      sendToken: String,
      segmentIndex: Int,
      segmentCount: Int,
  ): PendingIntent {
    val kind = if (action == ACTION_SENT) "sent" else "delivered"
    val intent =
        Intent(context, receiver).apply {
          this.action = action
          data = Uri.parse("smsgw://$kind/${Uri.encode(jobId)}/$sendToken/$segmentIndex")
          putExtra(EXTRA_JOB_ID, jobId)
          putExtra(EXTRA_SEND_TOKEN, sendToken)
          putExtra(EXTRA_SEGMENT_INDEX, segmentIndex)
          putExtra(EXTRA_SEGMENT_COUNT, segmentCount)
        }
    val requestCode = "$kind|$jobId|$sendToken|$segmentIndex".hashCode()
    // The delivery intent must be mutable: the carrier's status PDU is added as a fill-in extra.
    // It is explicit (component set), which is what Android 14+ requires of mutable intents.
    val flags =
        if (action == ACTION_SENT) {
          PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_ONE_SHOT
        } else if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
          PendingIntent.FLAG_MUTABLE
        } else {
          0
        }
    return PendingIntent.getBroadcast(context, requestCode, intent, flags)
  }
}
