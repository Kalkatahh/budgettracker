module.exports = {
    root: true,
    env: {
      browser: true,
      es2021: true,
      node: true,
    },
    parserOptions: {
      sourceType: 'module',
    },
    extends: [
      'plugin:vue/vue3-essential',
    ],
  };