// Outage path: Zenodo unreachable (mock scenario 'outage') — publishing still
// succeeds, the deposit fails through the retry path, then manual retry after
// "recovery" deposits without creating a duplicate.
describe('zenodo-down', () => {
  before(() => {
    cy.setPluginSettings({
      zenodo_enabled: 1,
      zenodo_sandbox: 1,
      zenodo_mode: 'mock',
      zenodo_mock_scenario: 'outage',
      zenodo_deposit_datasets: 1,
    });
    cy.db('delete from zenodo_deposits');
    cy.login();
  });

  it('publication is not blocked, deposit fails, manual retry deposits', () => {
    cy.ensureIssue().then((issueId) => {
      cy.createSubmission().then((submission) => {
        cy.publishSubmission(submission, issueId).then(
          ({ publishResponse, publication }) => {
            // Publish itself must succeed even though Zenodo is down.
            expect(publishResponse.status, JSON.stringify(publishResponse.body)).to.eq(200);

            // Backoff between the 5 attempts is 30+60+120+240 s in real time;
            // expire the queued reservations so the worker retries promptly —
            // the retry code path itself is still exercised end to end.
            const waitFailed = (deadline) => {
              cy.depositState(publication.id).then((state) => {
                if (state && state.status === 'failed') {
                  return;
                }
                expect(Date.now(), 'timed out waiting for failed').to.be.lessThan(deadline);
                cy.expireQueueBackoff();
                cy.wait(3000);
                waitFailed(deadline);
              });
            };
            waitFailed(Date.now() + 120000);

            cy.depositState(publication.id).then((state) => {
              expect(state.status).to.eq('failed');
              expect(state.attempts).to.eq(5);
            });

            // Zenodo "recovers": restore mock success and retry via the
            // article-page link (manager only).
            cy.setPluginSettings({
              zenodo_enabled: 1,
              zenodo_sandbox: 1,
              zenodo_mode: 'mock',
              zenodo_mock_scenario: 'success',
              zenodo_deposit_datasets: 1,
            });

            cy.visit(`/article/view/${submission.id}`);
            cy.get('.zenodo_deposit').should('contain', 'Deposit failed');
            cy.contains('.zenodo_deposit a', 'Retry deposit')
              .invoke('attr', 'href')
              .then((href) => {
                cy.request({ url: href, failOnStatusCode: false }).then((r) => {
                  expect(r.status).to.eq(200);
                  expect(r.body.status).to.eq(true);
                });
              });

            // Retried through the idempotent path: deposited, still a single
            // state row, no second deposition object in the DB.
            cy.waitDepositStatus(publication.id, 'deposited');
            cy.db(
              `select count(*) from zenodo_deposits where publication_id=${publication.id}`
            ).then((r) => {
              expect(r.stdout.trim()).to.eq('1');
            });
            cy.visit(`/article/view/${submission.id}`);
            cy.get('.zenodo_deposit').should('contain', 'Deposited to Zenodo');
          }
        );
      });
    });
  });
});
