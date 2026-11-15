{**
 * templates/settingsForm.tpl
 *
 * Zenodo Deposit plugin settings (per journal).
 * The API token is write-only: it is never rendered back into this form.
 *}
<script>
	$(function() {ldelim}
		$('#zenodoDepositSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="zenodoDepositSettingsForm" method="post"
	action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="zenodoDepositSettingsFormNotification"}

	{if $zenodoSandboxWarning}
		<div class="pkp_notification pkp_notification_warning">
			{translate key="plugins.generic.zenodoDeposit.settings.sandboxWarning"}
		</div>
	{/if}

	{fbvFormArea id="zenodoDepositSettingsArea"}
		{fbvElement type="checkbox" id="zenodo_enabled" value=1 checked=$zenodo_enabled label="plugins.generic.zenodoDeposit.settings.enabled"}
		{fbvElement type="checkbox" id="zenodo_sandbox" value=1 checked=$zenodo_sandbox label="plugins.generic.zenodoDeposit.settings.sandbox"}
		{fbvElement type="checkbox" id="zenodo_deposit_datasets" value=1 checked=$zenodo_deposit_datasets label="plugins.generic.zenodoDeposit.settings.depositDatasets"}

		{if $zenodo_token_configured}
			<p>{translate key="plugins.generic.zenodoDeposit.settings.tokenConfigured"}</p>
		{else}
			<p>{translate key="plugins.generic.zenodoDeposit.settings.tokenNotConfigured"}</p>
		{/if}
		{fbvElement type="text" id="zenodo_token" password=true label="plugins.generic.zenodoDeposit.settings.token"}
	{/fbvFormArea}

	{fbvFormButtons}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>
