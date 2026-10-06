import React, {useCallback, useEffect, useState} from 'react';
import {Alert, FlatList, StyleSheet, Text, View} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import {Button, Pill, Segmented} from '../components/ui';
import {colors, radius, shadow, space} from '../components/theme';
import {jobRepository} from '../storage/jobRepository';
import {logRepository} from '../storage/logRepository';
import {gatewayEvents} from '../services/events';
import type {GatewayJob, GatewayLog, JobStatus, LogLevel} from '../types/gateway';
import {maskPhone} from '../utils/mask';
import {formatDateTime} from '../utils/time';

type Tab = 'logs' | 'jobs';
type Filter = 'all' | LogLevel;

const LEVEL_COLOR: Record<LogLevel, string> = {
  debug: colors.faint,
  info: colors.info,
  warn: colors.warning,
  error: colors.danger,
};

const JOB_STATE: Record<JobStatus, {label: string; tone: 'success' | 'warning' | 'danger' | 'info' | 'neutral'}> = {
  RECEIVED: {label: 'Waiting', tone: 'neutral'},
  PROCESSING: {label: 'Starting', tone: 'info'},
  SENDING: {label: 'Sending', tone: 'info'},
  SENT_LOCAL: {label: 'Sent · confirming', tone: 'success'},
  ACK_PENDING: {label: 'Sent · confirming', tone: 'success'},
  COMPLETED: {label: 'Sent', tone: 'success'},
  ACK_REJECTED: {label: 'Sent (late)', tone: 'warning'},
  FAILED_LOCAL: {label: 'Failed', tone: 'danger'},
  FAILURE_ACK_PENDING: {label: 'Failed', tone: 'danger'},
  FAILED_COMPLETE: {label: 'Failed', tone: 'danger'},
  CANCELLED: {label: 'Skipped', tone: 'neutral'},
};

export default function LogsScreen() {
  const [tab, setTab] = useState<Tab>('jobs');
  const [filter, setFilter] = useState<Filter>('all');
  const [logs, setLogs] = useState<GatewayLog[]>([]);
  const [jobs, setJobs] = useState<GatewayJob[]>([]);

  const load = useCallback(async () => {
    if (tab === 'logs') {
      setLogs(await logRepository.list(400, filter === 'all' ? undefined : filter));
    } else {
      setJobs(await jobRepository.recent(200));
    }
  }, [tab, filter]);

  useEffect(() => {
    load();
    const a = logRepository.subscribe(load);
    const b = gatewayEvents.onChanged(load);
    return () => {
      a();
      b();
    };
  }, [load]);

  function onClear() {
    Alert.alert('Clear logs?', 'Job records are kept; only the log lines are removed.', [
      {text: 'Cancel', style: 'cancel'},
      {text: 'Clear', style: 'destructive', onPress: () => logRepository.clear()},
    ]);
  }

  return (
    <SafeAreaView style={s.root} edges={['bottom', 'left', 'right']}>
      <View style={s.header}>
        <Segmented<Tab>
          value={tab}
          onChange={setTab}
          options={[
            {value: 'jobs', label: 'Messages'},
            {value: 'logs', label: 'Events'},
          ]}
        />
        {tab === 'logs' ? (
          <Segmented<Filter>
            value={filter}
            onChange={setFilter}
            options={[
              {value: 'all', label: 'All'},
              {value: 'warn', label: 'Warnings'},
              {value: 'error', label: 'Errors'},
            ]}
          />
        ) : null}
      </View>
      {tab === 'logs' ? (
        <FlatList
          data={logs}
          keyExtractor={l => String(l.id)}
          contentContainerStyle={s.list}
          ListEmptyComponent={<Text style={s.empty}>Nothing here yet.</Text>}
          renderItem={({item}) => (
            <View style={s.item}>
              <View style={s.itemHead}>
                <Text style={[s.level, {color: LEVEL_COLOR[item.level]}]}>{item.level.toUpperCase()}</Text>
                <Text style={s.time}>{formatDateTime(item.timestamp)}</Text>
              </View>
              <Text style={s.event}>{item.event}</Text>
              {item.details ? <Text style={s.details}>{item.details}</Text> : null}
            </View>
          )}
          ListFooterComponent={logs.length ? <Button title="Clear logs" variant="secondary" onPress={onClear} /> : undefined}
        />
      ) : (
        <FlatList
          data={jobs}
          keyExtractor={j => String(j.id)}
          contentContainerStyle={s.list}
          ListEmptyComponent={<Text style={s.empty}>No messages yet. They appear here as soon as the portal sends some.</Text>}
          renderItem={({item}) => (
            <View style={s.item}>
              <View style={s.itemHead}>
                <Text style={s.event}>{maskPhone(item.phoneNumber)}</Text>
                <Pill label={JOB_STATE[item.status].label} tone={JOB_STATE[item.status].tone} />
              </View>
              <Text style={s.details}>
                Message #{item.serverMessageId}
                {item.simSlotUsed ? ` · SIM ${item.simSlotUsed}` : ''}
                {item.segmentCount && item.segmentCount > 1 ? ` · ${item.segmentCount} parts` : ''}
                {item.attempt > 1 ? ` · attempt ${item.attempt}` : ''}
              </Text>
              {item.failureMessage ? <Text style={[s.details, {color: colors.danger}]}>{item.failureMessage}</Text> : null}
              <Text style={s.time}>
                {item.sentAt ? `Sent ${formatDateTime(item.sentAt)}` : `Received ${formatDateTime(item.receivedAt)}`}
                {item.deliveredAt ? ' · delivered' : ''}
                {item.ackRetryCount ? ` · ack retries ${item.ackRetryCount}` : ''}
              </Text>
            </View>
          )}
        />
      )}
    </SafeAreaView>
  );
}

const s = StyleSheet.create({
  root: {flex: 1, backgroundColor: colors.bg},
  header: {paddingHorizontal: space.lg, paddingTop: space.md},
  list: {paddingHorizontal: space.lg, paddingBottom: space.xl},
  item: {
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: space.lg,
    marginBottom: space.sm,
    ...shadow,
  },
  itemHead: {flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4},
  level: {fontSize: 11, fontWeight: '800', letterSpacing: 0.6},
  time: {fontSize: 12, color: colors.faint, marginTop: 2},
  event: {fontSize: 15, fontWeight: '700', color: colors.text},
  details: {fontSize: 13, color: colors.muted, marginTop: 2},
  empty: {textAlign: 'center', color: colors.muted, marginTop: space.xl},
});
