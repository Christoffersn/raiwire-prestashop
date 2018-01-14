{extends "$layout"}

{block name="content"}
<section>
	<h3>{l s='Raiwire' mod='raiwire'}</h3>
	<form action="https://raiwire.com/payment/paymentwindow" method="post" id="raiwireForm">
	{foreach from=$paymentRequest key=k item=v}
			<input type="hidden" name="{$k|replace:'raiwire_':''}" value="{$v}">
	{/foreach}
	</form>
	<div class="raiwire_paymentwindow_container">
		<p class="payment_module">
			<a class="raiwire_btn raiwire_payment_content" title="{l s='Pay using Raiwire' mod='raiwire'}" href="javascript: raiwireForm.submit();"><span>Continue to RAIWIRE</span>
			</a>
		</p>
  </div>
  <script type="text/javascript">
  	document.getElementById("raiwireForm").submit();
  </script>
</section>
{/block}