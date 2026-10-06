package com.arete.smsgateway.sms

import android.app.Activity
import android.telephony.SmsManager

/**
 * Maps SmsManager sent-intent result codes to the app's clean error vocabulary. The JS layer
 * translates these into the LMS API's error_code values.
 *
 * Codes above 6 were added in API 30; they are compile-time ints so matching on the literal
 * values is safe on older devices (older radios simply never report them).
 */
object SmsErrorMapper {
  const val GENERIC_FAILURE = "GENERIC_FAILURE"
  const val RADIO_OFF = "RADIO_OFF"
  const val NO_SERVICE = "NO_SERVICE"
  const val NULL_PDU = "NULL_PDU"
  const val SMS_LIMIT_EXCEEDED = "SMS_LIMIT_EXCEEDED"
  const val FDN_CHECK_FAILED = "FDN_CHECK_FAILED"
  const val SIM_UNAVAILABLE = "SIM_UNAVAILABLE"
  const val PERMISSION_DENIED = "PERMISSION_DENIED"
  const val INVALID_NUMBER = "INVALID_NUMBER"
  const val NETWORK_ERROR = "NETWORK_ERROR"
  const val SHORT_CODE_BLOCKED = "SHORT_CODE_BLOCKED"
  const val SEND_EXCEPTION = "SEND_EXCEPTION"
  const val INVALID_MESSAGE = "INVALID_MESSAGE"

  fun fromResultCode(code: Int): String? =
      when (code) {
        Activity.RESULT_OK -> null
        SmsManager.RESULT_ERROR_GENERIC_FAILURE -> GENERIC_FAILURE
        SmsManager.RESULT_ERROR_RADIO_OFF -> RADIO_OFF
        SmsManager.RESULT_ERROR_NULL_PDU -> NULL_PDU
        SmsManager.RESULT_ERROR_NO_SERVICE -> NO_SERVICE
        SmsManager.RESULT_ERROR_LIMIT_EXCEEDED -> SMS_LIMIT_EXCEEDED
        SmsManager.RESULT_ERROR_FDN_CHECK_FAILURE -> FDN_CHECK_FAILED
        7, 8 -> SHORT_CODE_BLOCKED // RESULT_ERROR_SHORT_CODE_NOT_ALLOWED / NEVER_ALLOWED
        9 -> RADIO_OFF // RESULT_RADIO_NOT_AVAILABLE
        10, 17 -> NETWORK_ERROR // RESULT_NETWORK_REJECT / RESULT_NETWORK_ERROR
        11, 19 -> INVALID_NUMBER // RESULT_INVALID_ARGUMENTS / RESULT_INVALID_SMSC_ADDRESS
        14, 18 -> INVALID_MESSAGE // RESULT_INVALID_SMS_FORMAT / RESULT_ENCODING_ERROR
        20 -> PERMISSION_DENIED // RESULT_OPERATION_NOT_ALLOWED
        29, 30 -> NETWORK_ERROR // blocked during emergency / send retry failed
        in 100..199 -> ril(code)
        else -> GENERIC_FAILURE
      }

  private fun ril(code: Int): String =
      when (code) {
        100 -> RADIO_OFF // RESULT_RIL_RADIO_NOT_AVAILABLE
        101, 102, 112, 116, 121, 123 -> NETWORK_ERROR // retry-fail, reject, network err/not ready, call conflicts
        104, 110 -> INVALID_NUMBER // invalid arguments / invalid SMSC address
        106 -> SMS_LIMIT_EXCEEDED // RESULT_RIL_REQUEST_RATE_LIMITED
        107, 109 -> INVALID_MESSAGE // invalid SMS format / encoding error
        120 -> SIM_UNAVAILABLE // RESULT_RIL_SIM_ABSENT
        else -> GENERIC_FAILURE
      }
}
