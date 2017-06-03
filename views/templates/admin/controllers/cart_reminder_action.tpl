<div class="panel">
	{include file="helpers/kpi/kpi.tpl"
		id="kpi-cart"
		color="color1"
		icon="icon-shopping-cart"
		title="{l s='Recoverable Carts' mod='bringthecartback'}"
		subtitle="{l s='These last %s days' sprintf=$last_days_number mod='bringthecartback'}"
		value="{$recoverable_carts_number}"
		source=''
		chart=null
	}
{if isset($sent_emails)}
		<div class="alert alert-{if $sent_emails}success{else}warning{/if}" style="display:block;">
		{if $sent_emails}
			{l s='Sent e-mails: ' mod='bringthecartback'}{$sent_emails}
		{else}
			{l s='No e-mail sent. There was no recoverable carts: reminders were already sent or customer(s) might have made a purchase before or after abandoning retrieved carts.' mod='bringthecartback'}
		{/if}
		</div>
{/if}
		<p>
			<a class="btn btn-default" href="{$send_mail_action_url}">
				<i class="icon-envelope"></i> 
				{l s='Launch Mail Campaign' mod='bringthecartback'}
			</a>
		</p>
</div>