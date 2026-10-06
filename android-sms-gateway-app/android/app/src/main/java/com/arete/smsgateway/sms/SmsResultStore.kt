package com.arete.smsgateway.sms

import android.content.Context
import android.content.SharedPreferences
import org.json.JSONArray
import org.json.JSONObject

/**
 * Durable per-job send state, owned by native code.
 *
 * Sent/delivered broadcasts can arrive while the JS runtime is not running (or after the process
 * was killed and restarted just to deliver the broadcast). Recording the outcome here with
 * commit() means JS can always reconcile later and never has to guess whether an SMS went out.
 *
 * Each send attempt carries a unique sendToken; callbacks from an older attempt of the same job
 * are ignored, and the logical result is finalized exactly once.
 */
class SmsResultStore private constructor(context: Context) {

  data class Record(
      val jobId: String,
      val sendToken: String,
      val status: String,
      val errorCode: String?,
      val resultCode: Int,
      val segmentCount: Int,
      val segmentsDone: Set<Int>,
      val segmentsDelivered: Set<Int>,
      val startedAt: Long,
      val completedAt: Long,
      val deliveredAt: Long,
  ) {
    val isFinal: Boolean
      get() = status == STATUS_SENT || status == STATUS_FAILED

    fun toJson(): JSONObject =
        JSONObject()
            .put("jobId", jobId)
            .put("sendToken", sendToken)
            .put("status", status)
            .put("errorCode", errorCode ?: JSONObject.NULL)
            .put("resultCode", resultCode)
            .put("segmentCount", segmentCount)
            .put("segmentsDone", JSONArray(segmentsDone.toList()))
            .put("segmentsDelivered", JSONArray(segmentsDelivered.toList()))
            .put("startedAt", startedAt)
            .put("completedAt", completedAt)
            .put("deliveredAt", deliveredAt)

    companion object {
      fun fromJson(o: JSONObject): Record =
          Record(
              jobId = o.getString("jobId"),
              sendToken = o.getString("sendToken"),
              status = o.getString("status"),
              errorCode = if (o.isNull("errorCode")) null else o.getString("errorCode"),
              resultCode = o.optInt("resultCode", 0),
              segmentCount = o.optInt("segmentCount", 1),
              segmentsDone = o.optJSONArray("segmentsDone").toIntSet(),
              segmentsDelivered = o.optJSONArray("segmentsDelivered").toIntSet(),
              startedAt = o.optLong("startedAt", 0),
              completedAt = o.optLong("completedAt", 0),
              deliveredAt = o.optLong("deliveredAt", 0),
          )

      private fun JSONArray?.toIntSet(): Set<Int> {
        if (this == null) return emptySet()
        return (0 until length()).map { getInt(it) }.toSet()
      }
    }
  }

  private val prefs: SharedPreferences =
      context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

  @Synchronized
  fun begin(jobId: String, sendToken: String, segmentCount: Int): Record {
    val record =
        Record(
            jobId = jobId,
            sendToken = sendToken,
            status = STATUS_SENDING,
            errorCode = null,
            resultCode = 0,
            segmentCount = segmentCount,
            segmentsDone = emptySet(),
            segmentsDelivered = emptySet(),
            startedAt = System.currentTimeMillis(),
            completedAt = 0,
            deliveredAt = 0,
        )
    save(record)
    return record
  }

  /** Returns the record when this call finalized the logical message, otherwise null. */
  @Synchronized
  fun recordSegment(jobId: String, sendToken: String, segmentIndex: Int, resultCode: Int): Record? {
    val current = get(jobId) ?: return null
    if (current.sendToken != sendToken || current.isFinal) return null
    val error = SmsErrorMapper.fromResultCode(resultCode)
    val updated =
        if (error != null) {
          current.copy(
              status = STATUS_FAILED,
              errorCode = error,
              resultCode = resultCode,
              completedAt = System.currentTimeMillis(),
          )
        } else {
          val done = current.segmentsDone + segmentIndex
          if (done.size >= current.segmentCount) {
            current.copy(
                status = STATUS_SENT,
                segmentsDone = done,
                resultCode = resultCode,
                completedAt = System.currentTimeMillis(),
            )
          } else {
            current.copy(segmentsDone = done)
          }
        }
    save(updated)
    return if (updated.isFinal) updated else null
  }

  /** Returns the record once, when every segment's delivery report has arrived successfully. */
  @Synchronized
  fun recordDelivery(jobId: String, sendToken: String, segmentIndex: Int, delivered: Boolean): Record? {
    val current = get(jobId) ?: return null
    if (current.sendToken != sendToken || current.deliveredAt > 0 || !delivered) return null
    val done = current.segmentsDelivered + segmentIndex
    val updated =
        if (done.size >= current.segmentCount) {
          current.copy(segmentsDelivered = done, deliveredAt = System.currentTimeMillis())
        } else {
          current.copy(segmentsDelivered = done)
        }
    save(updated)
    return if (updated.deliveredAt > 0) updated else null
  }

  @Synchronized
  fun failImmediately(jobId: String, sendToken: String, errorCode: String): Record? {
    val current = get(jobId) ?: return null
    if (current.sendToken != sendToken || current.isFinal) return null
    val updated =
        current.copy(status = STATUS_FAILED, errorCode = errorCode, completedAt = System.currentTimeMillis())
    save(updated)
    return updated
  }

  @Synchronized
  fun get(jobId: String): Record? =
      prefs.getString(key(jobId), null)?.let {
        runCatching { Record.fromJson(JSONObject(it)) }.getOrNull()
      }

  @Synchronized
  fun all(): List<Record> {
    prune()
    return prefs.all.entries
        .filter { it.key.startsWith(KEY_PREFIX) }
        .mapNotNull { e -> (e.value as? String)?.let { runCatching { Record.fromJson(JSONObject(it)) }.getOrNull() } }
        .sortedBy { it.startedAt }
  }

  @Synchronized
  fun clear(jobId: String) {
    prefs.edit().remove(key(jobId)).commit()
  }

  private fun save(record: Record) {
    prefs.edit().putString(key(record.jobId), record.toJson().toString()).commit()
  }

  // Final records JS never cleared (e.g. after an uninstall of the JS DB) are dropped after a week.
  private fun prune() {
    val cutoff = System.currentTimeMillis() - RETENTION_MS
    val editor = prefs.edit()
    var changed = false
    for ((k, v) in prefs.all) {
      if (!k.startsWith(KEY_PREFIX) || v !is String) continue
      val r = runCatching { Record.fromJson(JSONObject(v)) }.getOrNull()
      if (r == null || (r.isFinal && r.completedAt in 1 until cutoff)) {
        editor.remove(k)
        changed = true
      }
    }
    if (changed) editor.commit()
  }

  private fun key(jobId: String) = KEY_PREFIX + jobId

  companion object {
    const val STATUS_SENDING = "sending"
    const val STATUS_SENT = "sent"
    const val STATUS_FAILED = "failed"

    private const val PREFS = "sms_gateway_results"
    private const val KEY_PREFIX = "job:"
    private const val RETENTION_MS = 7L * 24 * 60 * 60 * 1000

    @Volatile private var instance: SmsResultStore? = null

    fun get(context: Context): SmsResultStore =
        instance ?: synchronized(this) { instance ?: SmsResultStore(context).also { instance = it } }
  }
}
