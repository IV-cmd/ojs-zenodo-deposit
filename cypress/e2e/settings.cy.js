const MANAGE_URL =
  '/$$$call$$$/grid/settings/plugins/settings-plugin-grid/manage' +
  '?verb=settings&plugin=zenododepositplugin&category=generic';

describe('settings', () => {
  it('denies the manage route to anonymous users', () => {
    // OJS answers 200 + JSONMessage{status:false} for the gated grid route.
    cy.request({ url: MANAGE_URL, failOnStatusCode: false }).then((r) => {
      expect(r.body.status).to.eq(false);
    });
  });

  it('manager saves settings; the token field never echoes the secret', () => {
    cy.login();
    cy.csrfToken().then((token) => {
      cy.request({
        method: 'POST',
        url: MANAGE_URL + '&save=1',
        headers: { 'X-Csrf-Token': token },
        form: true,
        body: {
          csrfToken: token,
          zenodo_enabled: 1,
          zenodo_sandbox: 1,
          zenodo_deposit_datasets: 1,
          zenodo_token: 'cypress-test-token',
        },
        failOnStatusCode: false,
      }).then((save) => {
        expect(save.status).to.eq(200);
        expect(JSON.stringify(save.body)).to.not.contain('cypress-test-token');
      });
    });

    cy.db(
      `select setting_value from plugin_settings where plugin_name='zenododepositplugin' and setting_name='zenodo_token'`
    ).then((r) => {
      expect(r.stdout.trim()).to.eq('cypress-test-token');
    });

    cy.request(MANAGE_URL).then((form) => {
      const html = form.body.content || '';
      expect(html).to.contain('API token: configured');
      expect(html).to.contain('type="password"');
      expect(html).to.not.contain('cypress-test-token');
    });
  });
});
