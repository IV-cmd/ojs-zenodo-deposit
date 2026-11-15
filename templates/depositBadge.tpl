{**
 * templates/depositBadge.tpl
 *
 * Deposit status panel on the article page (M4). Shows the deposit state,
 * the Zenodo DOI link once deposited, and — for managers — the last error
 * and a retry action for a failed deposit. Never renders the API token or
 * raw API payloads.
 *}
<div class="item zenodo_deposit">
	<section class="sub_item">
		<h3 class="label">{translate key="plugins.generic.zenodoDeposit.deposit.status"}</h3>
		<div class="value">
			<p>
				{translate key="plugins.generic.zenodoDeposit.deposit.status.`$zenodoDepositStatus`"}
			</p>
			{if $zenodoDepositDoi}
				<p>
					{translate key="plugins.generic.zenodoDeposit.deposit.record"}:
					<a href="{$zenodoDepositRecordUrl}" target="_blank" rel="noopener">{$zenodoDepositDoi}</a>
				</p>
			{/if}
			{if $zenodoDepositError}
				<p><em>{translate key="plugins.generic.zenodoDeposit.deposit.lastError"}: {$zenodoDepositError|escape}</em></p>
			{/if}
			{if $zenodoDepositRetryUrl}
				<p><a href="{$zenodoDepositRetryUrl}">{translate key="plugins.generic.zenodoDeposit.deposit.retry"}</a></p>
			{/if}
		</div>
	</section>
</div>
