package com.arete.smsgateway.sms

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.telephony.SmsManager
import android.telephony.SubscriptionInfo
import android.telephony.SubscriptionManager
import androidx.core.content.ContextCompat

/** Active SIM subscriptions via the public SubscriptionManager API (needs READ_PHONE_STATE). */
object SimDirectory {

  data class Sim(
      val subscriptionId: Int,
      val slotIndex: Int,
      val carrierName: String?,
      val displayName: String?,
      val phoneNumber: String?,
      val countryIso: String?,
      val isEmbedded: Boolean,
  )

  fun hasPhoneStatePermission(context: Context): Boolean =
      ContextCompat.checkSelfPermission(context, Manifest.permission.READ_PHONE_STATE) ==
          PackageManager.PERMISSION_GRANTED

  @SuppressLint("MissingPermission")
  fun activeSims(context: Context): List<Sim> {
    if (!hasPhoneStatePermission(context)) throw SecurityException("READ_PHONE_STATE not granted")
    val manager = subscriptionManager(context) ?: return emptyList()
    val infos: List<SubscriptionInfo> = manager.activeSubscriptionInfoList ?: emptyList()
    return infos
        .map { info ->
          Sim(
              subscriptionId = info.subscriptionId,
              slotIndex = info.simSlotIndex,
              carrierName = info.carrierName?.toString()?.takeIf { it.isNotBlank() },
              displayName = info.displayName?.toString()?.takeIf { it.isNotBlank() },
              phoneNumber = phoneNumber(context, manager, info),
              countryIso = info.countryIso?.takeIf { it.isNotBlank() },
              isEmbedded = Build.VERSION.SDK_INT >= Build.VERSION_CODES.P && info.isEmbedded,
          )
        }
        .sortedBy { it.slotIndex }
  }

  fun activeSimCount(context: Context): Int =
      if (!hasPhoneStatePermission(context)) 0
      else runCatching { activeSims(context).size }.getOrDefault(0)

  fun defaultSmsSubscriptionId(): Int = SubscriptionManager.getDefaultSmsSubscriptionId()

  fun isActive(context: Context, subscriptionId: Int): Boolean =
      runCatching { activeSims(context).any { it.subscriptionId == subscriptionId } }.getOrDefault(false)

  fun smsManagerFor(context: Context, subscriptionId: Int): SmsManager {
    val useDefault = subscriptionId < 0
    return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
      val base = context.getSystemService(SmsManager::class.java)
      if (useDefault) base else base.createForSubscriptionId(subscriptionId)
    } else {
      @Suppress("DEPRECATION")
      if (useDefault) SmsManager.getDefault() else SmsManager.getSmsManagerForSubscriptionId(subscriptionId)
    }
  }

  private fun subscriptionManager(context: Context): SubscriptionManager? =
      context.getSystemService(SubscriptionManager::class.java)

  // The number is often not provisioned on the SIM, and on API 33+ reading it needs a permission
  // this app deliberately does not request, so null is a normal answer.
  @SuppressLint("MissingPermission", "HardwareIds")
  private fun phoneNumber(context: Context, manager: SubscriptionManager, info: SubscriptionInfo): String? =
      runCatching {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
              manager.getPhoneNumber(info.subscriptionId)
            } else {
              @Suppress("DEPRECATION") info.number
            }
          }
          .getOrNull()
          ?.takeIf { it.isNotBlank() }
}
