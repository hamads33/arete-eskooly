package com.arete.smsgateway.sms

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/** One broadcast per segment; the logical message is finalized once, in [SmsResultStore]. */
class SmsSentReceiver : BroadcastReceiver() {
  override fun onReceive(context: Context, intent: Intent) {
    if (intent.action != SmsSender.ACTION_SENT) return
    val jobId = intent.getStringExtra(SmsSender.EXTRA_JOB_ID) ?: return
    val token = intent.getStringExtra(SmsSender.EXTRA_SEND_TOKEN) ?: return
    val segment = intent.getIntExtra(SmsSender.EXTRA_SEGMENT_INDEX, 0)
    val finalRecord = SmsResultStore.get(context).recordSegment(jobId, token, segment, resultCode) ?: return
    SmsEvents.emitFinal(finalRecord)
  }
}
