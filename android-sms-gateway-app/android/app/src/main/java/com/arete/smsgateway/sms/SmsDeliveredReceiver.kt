package com.arete.smsgateway.sms

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.telephony.SmsMessage

/**
 * Carrier delivery reports. Informational only: the queue never waits for these, because many
 * carriers never send them.
 */
class SmsDeliveredReceiver : BroadcastReceiver() {
  override fun onReceive(context: Context, intent: Intent) {
    if (intent.action != SmsSender.ACTION_DELIVERED) return
    val jobId = intent.getStringExtra(SmsSender.EXTRA_JOB_ID) ?: return
    val token = intent.getStringExtra(SmsSender.EXTRA_SEND_TOKEN) ?: return
    val segment = intent.getIntExtra(SmsSender.EXTRA_SEGMENT_INDEX, 0)
    val count = intent.getIntExtra(SmsSender.EXTRA_SEGMENT_COUNT, 1)

    when (deliveryStatus(intent)) {
      Status.DELIVERED -> {
        val done = SmsResultStore.get(context).recordDelivery(jobId, token, segment, true) ?: return
        SmsEvents.emit(done.jobId, done.sendToken, "delivered", segmentCount = done.segmentCount)
      }
      Status.FAILED ->
          SmsEvents.emit(jobId, token, "delivery_failed", "DELIVERY_FAILED", segmentIndex = segment, segmentCount = count)
      Status.PENDING -> Unit
    }
  }

  private enum class Status { DELIVERED, PENDING, FAILED }

  // TP-Status (3GPP 23.040): 0x00-0x1F completed, 0x20-0x3F still trying, 0x40+ permanent error.
  private fun deliveryStatus(intent: Intent): Status {
    val pdu = intent.getByteArrayExtra("pdu") ?: return Status.DELIVERED
    val format = intent.getStringExtra("format") ?: "3gpp"
    val status = runCatching { SmsMessage.createFromPdu(pdu, format)?.status }.getOrNull() ?: return Status.DELIVERED
    return when {
      format == "3gpp2" -> if (status == 0) Status.DELIVERED else Status.FAILED
      status < 0x20 -> Status.DELIVERED
      status < 0x40 -> Status.PENDING
      else -> Status.FAILED
    }
  }
}
