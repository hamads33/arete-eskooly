/** Serializes async work inside one JS runtime (UI "Sync now" vs. the service's tick). */
export class Mutex {
  private tail: Promise<void> = Promise.resolve();
  private held = false;

  get isLocked(): boolean {
    return this.held;
  }

  async run<T>(fn: () => Promise<T>): Promise<T> {
    const previous = this.tail;
    let release!: () => void;
    this.tail = new Promise<void>(resolve => (release = resolve));
    await previous;
    this.held = true;
    try {
      return await fn();
    } finally {
      this.held = false;
      release();
    }
  }
}
