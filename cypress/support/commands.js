const COMPOSE_FILE = `${Cypress.config('projectRoot')}/../docker-compose.yml`;

const compose = (service, cmd) =>
  cy.exec(`docker compose -f ${COMPOSE_FILE} exec -T ${service} ${cmd}`, { log: false });

const mysql = (sql) =>
  compose('db', `mysql -u ojs -pojs_dev_password ojs -N -B -e "${sql}"`);

Cypress.Commands.add('db', mysql);

Cypress.Commands.add('login', (user = 'admin', password = 'adminadmin') => {
  cy.session([user, password], () => {
    cy.visit('/login');
    cy.get('#username').type(user);
    cy.get('#password').type(password);
    cy.get('#login button[type="submit"]').click();
    cy.location('pathname', { timeout: 15000 }).should('not.include', '/login');
    cy.window().its('pkp.currentUser.id').should('be.a', 'number');
  });
});

// X-CSRF-TOKEN required on every mutating API call; the backend pages
// expose it on pkp.currentUser.
Cypress.Commands.add('csrfToken', () =>
  cy.visit('/dashboard/editorial').then(() => cy.window().its('pkp.currentUser.csrfToken'))
);

Cypress.Commands.add('api', (method, path, body) =>
  cy.csrfToken().then((token) =>
    cy.request({
      method,
      url: `/api/v1/${path}`,
      headers: { 'X-Csrf-Token': token },
      body,
      failOnStatusCode: false,
    })
  )
);

// Delete then insert plugin settings; values must stay inside double quotes.
Cypress.Commands.add('setPluginSettings', (settings) => {
  const entries = Object.entries(settings);
  const values = entries
    .map(([name, value]) => `('zenododepositplugin', 1, '${name}', '${value}', 'string')`)
    .join(',');

  return mysql(
    `delete from plugin_settings where plugin_name='zenododepositplugin' and setting_name like 'zenodo_%';` +
      `insert into plugin_settings (plugin_name, context_id, setting_name, setting_value, setting_type) values ${values};`
  );
});

Cypress.Commands.add('depositState', (publicationId) =>
  mysql(
    `select status, ifnull(deposition_id,''), ifnull(doi,''), attempts, ifnull(last_error,'') ` +
      `from zenodo_deposits where publication_id=${publicationId}`
  ).then((result) => {
    const [status, depositionId, doi, attempts, lastError] = result.stdout.trim().split('\t');
    return status === undefined
      ? null
      : { status, depositionId, doi, attempts: Number(attempts), lastError };
  })
);

Cypress.Commands.add('waitDepositStatus', (publicationId, expected, deadline = Date.now() + 90000) => {
  cy.depositState(publicationId).then((state) => {
    if (state && state.status === expected) {
      return;
    }

    const current = state ? state.status : 'missing';
    expect(Date.now(), `timed out waiting for status "${expected}" (currently "${current}")`).to.be.lessThan(deadline);
    cy.wait(2000);
    cy.waitDepositStatus(publicationId, expected, deadline);
  });
});

// Force queued retry attempts to run immediately instead of waiting out the
// exponential backoff — the retry path is still exercised end to end.
Cypress.Commands.add('expireQueueBackoff', () =>
  mysql(`update jobs set reserved_at=null, available_at=unix_timestamp() where reserved_at is not null or available_at > unix_timestamp()`)
);

// Publishing needs a published issue and a late-stage submission; the issues
// API is read-only in 3.5, so the issue is seeded through SQL.
Cypress.Commands.add('ensureIssue', () =>
  mysql(`select issue_id from issues where journal_id=1 limit 1`).then((result) => {
    if (result.stdout.trim() !== '') {
      return Number(result.stdout.trim());
    }
    return mysql(
      `insert into issues (journal_id, volume, number, year, published, date_published, show_title, url_path) ` +
        `values (1,1,'1',2026,1,now(),1,'issue-1'); select last_insert_id()`
    ).then((inserted) => {
      const issueId = Number(inserted.stdout.trim().split('\n').pop());
      return mysql(
        `update journals set current_issue_id=${issueId} where journal_id=1;` +
          `insert into issue_settings (issue_id, locale, setting_name, setting_value) values (${issueId},'en','title','Issue 1')`
      ).then(() => issueId);
    });
  })
);

Cypress.Commands.add('createSubmission', () =>
  cy
    .api('POST', 'submissions', {
      sectionId: 1,
      userGroupId: 2,
      locale: 'en',
    })
    .then((response) => {
      expect(response.status, JSON.stringify(response.body)).to.eq(200);
      return response.body;
    })
);

Cypress.Commands.add('publishSubmission', (submission, issueId) =>
  cy
    .api('GET', `submissions/${submission.id}/publications`)
    .then((listResponse) => {
      const publication = listResponse.body.items[0];
      return cy
        .api('PUT', `submissions/${submission.id}/publications/${publication.id}`, {
          issueId,
          sectionId: 1,
          version: publication.version,
          title: { en: 'Cypress deposit article' },
          abstract: { en: 'Cypress deposit abstract.' },
        })
        .then((response) => {
          expect(response.status, JSON.stringify(response.body)).to.eq(200);
          return cy.api(
            'POST',
            `submissions/${submission.id}/publications/${publication.id}/contributors`,
            {
              givenName: { en: 'Ada' },
              familyName: { en: 'Lovelace' },
              country: 'US',
              userGroupId: 14,
              email: 'ada@example.com',
              includeInBrowse: true,
              seq: 1,
            }
          );
        })
        .then((contributorResponse) => {
          expect(contributorResponse.status).to.eq(200);
          return mysql(
            `update submissions set stage_id=5 where submission_id=${submission.id}`
          ).then(() =>
            cy.api(
              'PUT',
              `submissions/${submission.id}/publications/${publication.id}/publish`,
              {}
            )
            .then((publishResponse) => ({
              publishResponse,
              publication,
            }))
          );
        });
    })
);
