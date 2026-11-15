const { defineConfig } = require('cypress');

module.exports = defineConfig({
  e2e: {
    baseUrl: 'http://localhost:8080/index.php/zdj',
    specPattern: 'e2e/**/*.cy.js',
    supportFile: 'support/e2e.js',
    video: false,
  },
  env: {
    journalPath: 'zdj',
    adminUser: 'admin',
    adminPassword: 'adminadmin',
  },
});
