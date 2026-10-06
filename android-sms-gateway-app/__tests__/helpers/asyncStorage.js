const store = new Map();

const AsyncStorage = {
  getItem: async k => (store.has(k) ? store.get(k) : null),
  setItem: async (k, v) => {
    store.set(k, v);
  },
  removeItem: async k => {
    store.delete(k);
  },
  getMany: async keys => Object.fromEntries(keys.map(k => [k, store.has(k) ? store.get(k) : null])),
  setMany: async entries => Object.entries(entries).forEach(([k, v]) => store.set(k, v)),
  removeMany: async keys => keys.forEach(k => store.delete(k)),
  getAllKeys: async () => [...store.keys()],
  clear: async () => store.clear(),
};

module.exports = {__esModule: true, default: AsyncStorage};
