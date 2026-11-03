import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
Alpine.plugin(focus);
Alpine.data('landingPage', (config = {}) => ({
    quick: null, quickVariant: '', quickLoading: false,
    async openQuick(id) {
        if(!this.config.routes?.quick)return;
        this.quickLoading=true;this.quick=null;this.quickVariant='';
        try { const response=await fetch(this.config.routes.quick.replace(/0$/,String(id))+'?lang='+this.config.locale,{headers:{Accept:'application/json'}});if(!response.ok)throw new Error(this.config.messages.failed);this.quick=await response.json();this.$nextTick(()=>this.$refs.closeQuick?.focus()); } catch(e) {this.failure=e.message;} finally {this.quickLoading=false;}
    },
    closeQuick() {this.quick=null;},
    config, mode: config.mode || 'inline', mobile: matchMedia('(max-width:767px)').matches,
    orderOpen: false, packageId: '', division: '', district: '', upazila: '', area: '',
    districts: [], upazilas: [], areas: [], methods: [], shipping: '', totals: null,
    busy: false, pending: 0, serial: Promise.resolve(), failure: '', notice: '',
    phoneCode: '', reminders: false, reminderEmail: '', touched: false, resizeHandler: null,
    init() {
        this.resizeHandler = () => this.mobile = matchMedia('(max-width:767px)').matches;
        window.addEventListener('resize', this.resizeHandler);
        this.$nextTick(() => { this.packageId = this.$refs.orderForm?.querySelector('[name=package_id]:checked')?.value || ''; });
    },
    destroy() { window.removeEventListener('resize', this.resizeHandler); },
    isModal() { return this.mode === 'popup' || (this.mode === 'sticky' && this.mobile); },
    openOrder() { this.orderOpen = true; this.$nextTick(() => { if (!this.isModal()) this.$refs.orderForm?.scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion:reduce)').matches?'instant':'smooth'}); this.$refs.orderForm?.querySelector('[name=name]')?.focus({preventScroll:true}); }); },
    closeOrder() { if(this.quick){this.closeQuick();return;} this.orderOpen = false; },
    money(value) { return new Intl.NumberFormat(this.config.locale === 'bn' ? 'bn-BD' : 'en-BD', {style:'currency',currency:this.config.currency || 'BDT'}).format(value/100); },
    data() { const data = new FormData(this.$refs.orderForm); data.set('package_id',this.packageId); data.set('shipping_method_id',this.shipping); data.set('quote_hash',this.totals?.quote_hash||''); return data; },
    enqueue(action) {
        this.pending++; this.busy = true;
        this.serial = this.serial.catch(()=>{}).then(async()=>{try { await action(); } catch(error) { this.failure=error.message || this.config.messages?.failed || 'Request failed'; } finally { this.pending--; this.busy=this.pending>0; }});
        return this.serial;
    },
    async request(url, data) {
        const headers={'Accept':'application/json','X-CSRF-TOKEN':this.config.csrf};
        let payload=data;
        if (!(data instanceof FormData)) { headers['Content-Type']='application/json';payload=JSON.stringify({...data,_locale:this.config.locale}); }
        const response=await fetch(url,{method:'POST',headers,body:payload});
        const body=await response.json();
        if (!response.ok) throw new Error(Object.values(body.errors||{}).flat().join(' ') || body.message || this.config.messages.failed);
        return body;
    },
    async children(parent) {
        if(!parent)return [];
        const response=await fetch(this.config.routes.children+'?parent='+encodeURIComponent(parent)+'&lang='+encodeURIComponent(this.config.locale));
        if(!response.ok)throw new Error(this.config.messages.failed);
        return response.json();
    },
    divisionChanged() { this.district='';this.upazila='';this.area='';this.districts=[];this.upazilas=[];this.areas=[];this.methods=[];this.shipping='';this.totals=null; const id=this.division;this.enqueue(async()=>{this.districts=await this.children(id);}); },
    districtChanged() { this.upazila='';this.area='';this.upazilas=[];this.areas=[];this.methods=[];this.shipping='';this.totals=null;const id=this.district;this.enqueue(async()=>{this.upazilas=await this.children(id);});this.loadMethods(); },
    upazilaChanged() { this.area='';this.areas=[];const id=this.upazila;this.enqueue(async()=>{this.areas=await this.children(id);}); },
    formChanged(event) {
        if(!this.touched){this.touched=true;this.enqueue(()=>this.request(this.config.routes.touched,{}));}
        const name=event.target.name || '';
        if(name==='package_id'||name==='units'||name==='bump'||name.startsWith('options[')) { this.totals=null;this.loadMethods(); }
        if(this.reminders&&['name','phone','email'].includes(name))this.saveReminder();
    },
    loadMethods() {
        if(!this.packageId||!this.district)return;
        const data=this.data();this.failure='';
        this.enqueue(async()=>{this.methods=await this.request(this.config.routes.shipping,data);if(!this.methods.some(m=>String(m.id)===String(this.shipping)))this.shipping=this.methods.length?String(this.methods[0].id):'';await this.$nextTick();if(this.shipping!=='')await this.performQuote();});
    },
    quote() { if(this.shipping==='')return;this.enqueue(()=>this.performQuote()); },
    async performQuote() { this.failure='';this.totals=await this.request(this.config.routes.quote,this.data()); },
    async submitOrder() {
        await this.serial;if(!this.$refs.orderForm.reportValidity()||!this.totals)return;
        this.failure='';const data=this.data();
        this.enqueue(async()=>{const result=await this.request(this.config.routes.place,data);if(result.redirect)location.assign(result.redirect);});
    },
    sendCode() { const phone=this.$refs.orderForm.elements.namedItem('phone')?.value||'';this.enqueue(async()=>{await this.request(this.config.routes.sendCode,{phone});this.notice=this.config.messages.codeQueued;}); },
    verifyCode() { const phone=this.$refs.orderForm.elements.namedItem('phone')?.value||'',code=this.phoneCode;this.enqueue(async()=>{await this.request(this.config.routes.verifyCode,{phone,code});this.notice=this.config.messages.verified;this.phoneCode='';}); },
    saveReminder() {
        const form=this.$refs.orderForm,enabled=this.reminders;
        if(enabled&&!this.reminderEmail)return;
        const data={enabled,consent:enabled,channels:{email:true},name:form.elements.namedItem('name')?.value||'',phone:form.elements.namedItem('phone')?.value||'',email:this.reminderEmail};
        this.enqueue(async()=>{await this.request(this.config.routes.recovery,data);this.notice=enabled?this.config.messages.saved:'';});
    }
}));
window.Alpine=Alpine;
Alpine.start();
