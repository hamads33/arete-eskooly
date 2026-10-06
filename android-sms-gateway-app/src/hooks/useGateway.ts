import {useEffect} from 'react';
import {AppState} from 'react-native';
import {useGatewayStore} from '../store/gatewayStore';

/** Keeps the store fresh while a screen is visible: on mount, on resume, and every few seconds. */
export function useGatewayRefresh(intervalMs = 5_000) {
  const refresh = useGatewayStore(s => s.refresh);
  useEffect(() => {
    refresh();
    const timer = setInterval(() => {
      if (AppState.currentState === 'active') {
        refresh();
      }
    }, intervalMs);
    const sub = AppState.addEventListener('change', s => {
      if (s === 'active') {
        refresh();
      }
    });
    return () => {
      clearInterval(timer);
      sub.remove();
    };
  }, [refresh, intervalMs]);
}
