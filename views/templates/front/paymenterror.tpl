{extends "$layout"}

{block name="content"}
<div>
    <p class="alert alert-warning warning">{l s='Your payment failed' mod='raiwire'} <strong>"{$paymenterror|escape:'htmlall':'UTF-8'}"</strong>
    </p>
</div>
{/block}