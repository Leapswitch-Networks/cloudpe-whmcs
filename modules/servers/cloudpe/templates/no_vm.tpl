{if $cloudpe_message}
    <div class="alert alert-{$cloudpe_message_type|default:'info'} alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        {$cloudpe_message}
    </div>
{/if}
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> {$message}
</div>
