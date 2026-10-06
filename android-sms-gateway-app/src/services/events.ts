type Listener = () => void;

const listeners = new Set<Listener>();

/** Fired whenever the engine changed persisted state, so the UI store can re-read it. */
export const gatewayEvents = {
  emitChanged(): void {
    listeners.forEach(l => {
      try {
        l();
      } catch {
        // A UI listener must never break the engine.
      }
    });
  },
  onChanged(l: Listener): () => void {
    listeners.add(l);
    return () => listeners.delete(l);
  },
};
