@if(store_setting('recovery_enabled', false))
<div class="mt-6 rounded-xl border p-4" x-data="{
 enabled:false, channels:{email:true,sms:false,whatsapp:false}, busy:false, message:'', code:'', phoneVerified:false, serial:Promise.resolve(),
 enqueue(action){this.serial=this.serial.catch(()=>{}).then(action)},
 async request(url,data){let response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@js(csrf_token())},body:JSON.stringify(data)});let body=await response.json();if(!response.ok)throw Error(Object.values(body.errors||{}).flat().join(' ')||@js(__('marketing.recovery_failed')));return body},
 capture(){let form=this.$el.closest('form');let data={enabled:this.enabled,consent:this.enabled,channels:{...this.channels},name:form.elements.namedItem('name').value,email:form.elements.namedItem('email').value,phone:form.elements.namedItem('phone').value};this.enqueue(async()=>{this.busy=true;try{let result=await this.request(@js(route('recovery.capture')),data);this.phoneVerified=result.phone_verified;this.message=data.enabled?@js(__('marketing.recovery_saved')):''}catch(e){this.message=e.message}finally{this.busy=false}})},
 sendCode(){this.capture();this.enqueue(async()=>{try{await this.request(@js(route('recovery.code.send')),{});this.message=@js(__('marketing.recovery_code_queued'))}catch(e){this.message=e.message}})},
 verifyCode(){let code=this.code;this.enqueue(async()=>{try{await this.request(@js(route('recovery.code.verify')),{code});this.phoneVerified=true;this.code='';this.message=@js(__('marketing.recovery_phone_verified'))}catch(e){this.message=e.message}})}
}" @recovery-contact-change.window.debounce.800ms="if(enabled)capture()">
<label class="flex min-h-11 items-start gap-3"><input type="checkbox" x-model="enabled" @change="capture()" class="mt-1"><span>{{ __('marketing.recovery_consent') }}</span></label>
<p class="mt-2 text-sm text-stone-600">{{ __('marketing.recovery_consent_help') }}</p>
<div x-show="enabled" x-cloak class="mt-4 space-y-4">
<div class="flex flex-wrap gap-5">@foreach(['email','sms','whatsapp'] as $channel)<label class="flex min-h-11 items-center gap-2"><input type="checkbox" x-model="channels.{{ $channel }}" @change="capture()">{{ __('marketing.channel_'.$channel) }}</label>@endforeach</div>
<div x-show="channels.sms||channels.whatsapp"><button type="button" @click="sendCode()" :disabled="busy||phoneVerified" class="min-h-11 rounded-lg border px-4">{{ __('marketing.recovery_send_code') }}</button><div class="mt-3 flex flex-wrap gap-3" x-show="!phoneVerified"><label>{{ __('orders.code') }}<input type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" x-model="code" class="mx-2 w-28 rounded-lg border p-3"></label><button type="button" @click="verifyCode()" :disabled="busy" class="min-h-11 rounded-lg border px-4">{{ __('marketing.recovery_verify') }}</button></div></div>
</div><p x-text="message" role="status" class="mt-3 text-sm"></p>
</div>
@endif
