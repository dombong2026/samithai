<div class="toast-stack" role="status" aria-live="polite">
@if(session('success'))<div class="toast"><span>{{ session('success') }}</span><button type="button" aria-label="Dismiss notification" data-toast-dismiss>✕</button></div>@endif
@if($errors->any())<div class="toast error-toast"><div><strong>Unable to save</strong><div class="tiny">{{ $errors->first() }}</div></div><button type="button" aria-label="Dismiss error" data-toast-dismiss>✕</button></div>@endif
</div>
<div id="global-row-menu" class="row-menu" role="menu"></div>
<dialog id="confirmation-modal"><div class="modal-head"><h2>Confirm action</h2><button class="icon-btn" type="button" onclick="this.closest('dialog').close()" aria-label="Close">✕</button></div><div class="modal-content"><p data-confirm-message>Are you sure?</p></div><div class="modal-foot"><button class="btn" type="button" onclick="this.closest('dialog').close()">Cancel</button><button class="btn danger" type="button" data-confirm-yes>Confirm</button></div></dialog>
