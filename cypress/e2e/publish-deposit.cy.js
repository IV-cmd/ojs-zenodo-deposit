// Happy path: publish → deposit queued → mock Zenodo deposits → article-page
// badge shows the Zenodo DOI → the API exposes zenodoDeposit.
describe('publish-deposit', () => {
  before(() => {
    cy.setPluginSettings({
      zenodo_enabled: 1,
      zenodo_sandbox: 1,
      zenodo_mode: 'mock',
      zenodo_mock_scenario: 'success',
      zenodo_deposit_datasets: 1,
    });
    cy.db('delete from zenodo_deposits');
    cy.login();
  });

  it('publishes, deposits, and shows the Zenodo DOI badge', () => {
    cy.ensureIssue().then((issueId) => {
      cy.createSubmission().then((submission) => {
        cy.publishSubmission(submission, issueId).then(
          ({ publishResponse, publication }) => {
            expect(publishResponse.status, JSON.stringify(publishResponse.body)).to.eq(200);

            cy.waitDepositStatus(publication.id, 'deposited');
            cy.depositState(publication.id).then((state) => {
              expect(state.doi).to.match(/^10\.5072\/zenodo\./);
            });

            cy.visit(`/article/view/${submission.id}`);
            cy.get('.zenodo_deposit').should('contain', 'Zenodo deposit');
            cy.get('.zenodo_deposit').should('contain', 'Deposited to Zenodo');
            cy.get('.zenodo_deposit a')
              .should('contain', '10.5072/zenodo.')
              .and('have.attr', 'href')
              .and('include', 'zenodo');

            cy.api('GET', `submissions/${submission.id}/publications/${publication.id}`).then((r) => {
              expect(r.status).to.eq(200);
              expect(r.body.zenodoDeposit.status).to.eq('deposited');
              expect(r.body.zenodoDeposit.doi).to.match(/^10\.5072\/zenodo\./);
              expect(r.body.zenodoDeposit).to.not.have.property('token');
            });
          }
        );
      });
    });
  });
});
