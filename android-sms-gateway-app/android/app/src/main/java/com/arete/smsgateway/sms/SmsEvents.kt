package com.arete.smsgateway.sms

import com.facebook.react.bridge.Arguments
import com.facebook.react.bridge.WritableMap

/**
 * Hands native SMS outcomes to the live TurboModule when one exists. When JS is not running the
 * event is simply dropped: the outcome is already durable in [SmsResultStore], and JS reconciles
 * from there on its next cycle.
 */
object SmsEvents {
  @Volatile var sink: ((WritableMap) -> Unit)? = null

  fun emit(
      jobId: String,
      sendToken: String,
      status: String,
      errorCode: String? = null,
      resultCode: Int = 0,
      segmentIndex: Int = -1,
      segmentCount: Int = 0,
  ) {
    val target = sink ?: return
    val map =
        Arguments.createMap().apply {
          putString("jobId", jobId)
          putString("sendToken", sendToken)
          putString("status", status)
          if (errorCode == null) putNull("errorCode") else putString("errorCode", errorCode)
          putInt("resultCode", resultCode)
          putInt("segmentIndex", segmentIndex)
          putInt("segmentCount", segmentCount)
          putDouble("timestamp", System.currentTimeMillis().toDouble())
        }
    runCatching { target(map) }
  }

  fun emitFinal(record: SmsResultStore.Record) =
      emit(
          jobId = record.jobId,
          sendToken = record.sendToken,
          status = record.status,
          errorCode = record.errorCode,
          resultCode = record.resultCode,
          segmentCount = record.segmentCount,
      )
}
