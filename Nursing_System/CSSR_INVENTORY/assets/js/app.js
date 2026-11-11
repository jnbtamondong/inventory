function closeImageModal(){var modal=document.getElementById('imageModal');if(!modal)return;modal.classList.remove('open');modal.setAttribute('aria-hidden','true');var image=modal.querySelector('img');if(image)image.src=''}
function openImage(src){var modal=document.getElementById('imageModal');if(!modal)return;if(modal.parentElement!==document.body)document.body.appendChild(modal);var image=modal.querySelector('img');if(image)image.src=src;modal.classList.add('open');modal.setAttribute('aria-hidden','false');if(!modal.dataset.closeReady){modal.addEventListener('click',function(event){if(event.target===modal||event.target.closest('[data-image-modal-close]'))closeImageModal()});modal.dataset.closeReady='1'}}
function openImageGallery(images,index){
    var modal=document.getElementById('imageModal');
    if(!modal||!Array.isArray(images)||!images.length)return;
    modal._gallery=images;modal._galleryIndex=Number(index)||0;
    function show(step){
        modal._galleryIndex=(modal._galleryIndex+step+images.length)%images.length;
        modal.querySelector('img').src=images[modal._galleryIndex];
        var counter=modal.querySelector('[data-gallery-counter]');if(counter)counter.textContent=(modal._galleryIndex+1)+' / '+images.length;
        modal.querySelectorAll('.image-gallery-nav').forEach(function(button){button.hidden=images.length<2});
    }
    var previous=modal.querySelector('[data-gallery-prev]'),next=modal.querySelector('[data-gallery-next]');
    if(previous)previous.onclick=function(){show(-1)};
    if(next)next.onclick=function(){show(1)};
    show(0);openImage(images[modal._galleryIndex]);
    var image=modal.querySelector('img'),startX=0;
    image.ontouchstart=function(event){startX=event.changedTouches[0].clientX};
    image.ontouchend=function(event){var distance=event.changedTouches[0].clientX-startX;if(Math.abs(distance)>45)show(distance>0?-1:1)};
}
document.addEventListener('keydown',function(event){var modal=document.getElementById('imageModal');if(!modal?.classList.contains('open'))return;if(event.key==='Escape')closeImageModal();if(event.key==='ArrowLeft')modal.querySelector('[data-gallery-prev]')?.click();if(event.key==='ArrowRight')modal.querySelector('[data-gallery-next]')?.click()});
function updateRequestLotPicture(select){
    if(!select||!select.matches('.approved-lot-select[data-picture-target]'))return;
    var target=document.getElementById(select.dataset.pictureTarget);
    if(!target)return;
    var option=select.options[select.selectedIndex];
    var src=select.value?((option&&option.dataset.image)||''):(target.dataset.fallbackImage||'');
    target.replaceChildren();
    if(src){
        var img=document.createElement('img');
        img.className='request-detail-picture';
        img.src=src;
        img.alt='Selected lot image';
        img.tabIndex=0;
        img.setAttribute('role','button');
        img.addEventListener('click',function(){openImage(img.src)});
        img.addEventListener('keydown',function(event){if(event.key==='Enter'||event.key===' '){event.preventDefault();openImage(img.src)}});
        target.appendChild(img);
    }else{
        var empty=document.createElement('span');
        empty.className='request-detail-picture-empty';
        empty.textContent='IMG';
        target.appendChild(empty);
    }
}
function updateRequestLotStock(select){
    if(!select||!select.dataset.stockTarget)return;
    var target=document.getElementById(select.dataset.stockTarget);
    if(!target)return;
    var option=select.options[select.selectedIndex];
    target.textContent=select.value&&option?String(Number(option.dataset.stock||0)):'—';
}
function positionImageHoverPreview(preview,event){var gap=18,w=preview.offsetWidth||360,h=preview.offsetHeight||360,x=event.clientX+gap,y=event.clientY+gap;if(x+w>window.innerWidth-10)x=event.clientX-w-gap;if(y+h>window.innerHeight-10)y=window.innerHeight-h-10;preview.style.left=Math.max(10,x)+'px';preview.style.top=Math.max(10,y)+'px'}
document.addEventListener('mouseover',function(event){var image=event.target.closest('img[data-image-hover-preview]');if(!image)return;var preview=document.getElementById('imageHoverPreview');if(!preview){preview=document.createElement('img');preview.id='imageHoverPreview';preview.className='image-hover-preview';preview.alt='Larger item preview';document.body.appendChild(preview)}preview.src=image.currentSrc||image.src;preview.style.display='block';positionImageHoverPreview(preview,event)});
document.addEventListener('mousemove',function(event){var image=event.target.closest('img[data-image-hover-preview]');var preview=document.getElementById('imageHoverPreview');if(image&&preview&&preview.style.display==='block')positionImageHoverPreview(preview,event)});
document.addEventListener('mouseout',function(event){var image=event.target.closest('img[data-image-hover-preview]');if(!image)return;var preview=document.getElementById('imageHoverPreview');if(preview)preview.style.display='none'});
function printPage(){window.print()}
function startLiveClock(){
    var clock=document.getElementById('livePhilippineClock');
    if(!clock)return;
    var formatter=new Intl.DateTimeFormat('en-US',{timeZone:clock.dataset.timeZone||'Asia/Manila',month:'short',day:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
    function tick(){clock.textContent=formatter.format(new Date()).replace(/, (\d{2}:)/,' $1')}
    tick();
    window.setInterval(tick,1000);
}
function positionRowActionMenu(menu){
    if(!menu||!menu.open)return;
    var trigger=menu.querySelector('summary');
    var list=menu.querySelector(':scope > .row-action-list');
    if(!trigger||!list)return;
    var gap=4;
    var triggerRect=trigger.getBoundingClientRect();
    var listWidth=list.offsetWidth||190;
    var listHeight=list.offsetHeight||0;
    var left=Math.min(window.innerWidth-listWidth-8,Math.max(8,triggerRect.right-listWidth));
    var spaceBelow=window.innerHeight-triggerRect.bottom-gap;
    var top=spaceBelow>=listHeight
        ? triggerRect.bottom+gap
        : Math.max(8,triggerRect.top-listHeight-gap);
    list.style.left=left+'px';
    list.style.top=top+'px';
}
document.addEventListener('toggle',function(event){
    var menu=event.target;
    if(!menu.matches||!menu.matches('.row-action-menu'))return;
    if(menu.open){
        document.querySelectorAll('.row-action-menu[open]').forEach(function(other){if(other!==menu)other.open=false});
        positionRowActionMenu(menu);
    }
},true);
document.addEventListener('click',function(event){
    document.querySelectorAll('.row-action-menu[open]').forEach(function(menu){
        if(!menu.contains(event.target))menu.open=false;
    });
});
window.addEventListener('resize',function(){document.querySelectorAll('.row-action-menu[open]').forEach(positionRowActionMenu)});
window.addEventListener('scroll',function(){document.querySelectorAll('.row-action-menu[open]').forEach(positionRowActionMenu)},true);
function filterTable(inputId,tableId){var input=document.getElementById(inputId),table=document.getElementById(tableId);if(!input||!table)return;input.addEventListener('input',function(){var q=input.value.toLowerCase();table.querySelectorAll('tbody tr').forEach(function(row){row.style.display=row.textContent.toLowerCase().includes(q)?'':'none'})})}
function previewUpload(inputId,imgId){var input=document.getElementById(inputId),img=document.getElementById(imgId);if(!input||!img)return;input.addEventListener('change',function(){var f=input.files&&input.files[0];if(f){img.src=URL.createObjectURL(f);img.style.display='block'}})}
function showConfirm(message,onConfirm,options){
    options=options||{};
    var modal=document.getElementById('confirmModal');
    if(!modal){if(options.alert){alert(message);onConfirm();return}if(confirm(message)){onConfirm()}return}
    if(modal.parentElement!==document.body)document.body.appendChild(modal);
    var previousFocus=document.activeElement;
    var messageEl=document.getElementById('confirmMessage');
    var ok=modal.querySelector('[data-confirm-ok]');
    var cancel=modal.querySelector('[data-confirm-cancel]');
    var previousCancelText=cancel.textContent;
    var previousCancelDisplay=cancel.style.display;
    var previousOkText=ok.textContent;
    var close=function(){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');ok.onclick=null;cancel.onclick=null;modal.onkeydown=null;cancel.textContent=previousCancelText;cancel.style.display=previousCancelDisplay;ok.textContent=previousOkText;if(previousFocus&&previousFocus.isConnected)previousFocus.focus()};
    modal.onkeydown=function(event){
        if(event.key!=='Tab')return;
        var controls=Array.from(modal.querySelectorAll('button')).filter(function(button){return !button.disabled&&button.getClientRects().length});
        var first=controls[0],last=controls[controls.length-1];
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}
        else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
    };
    messageEl.textContent=message;
    cancel.textContent=message==='Do you want to print this request?'?'No':previousCancelText;
    cancel.style.display=options.alert?'none':previousCancelDisplay;
    ok.textContent=options.alert?'Okay':previousOkText;
    ok.onclick=function(){close();onConfirm()};
    cancel.onclick=close;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden','false');
    ok.focus();
}
function showToastMessage(type,message){
    var stack=document.querySelector('.toast-stack');
    if(!stack){
        stack=document.createElement('div');
        stack.className='toast-stack';
        document.body.appendChild(stack);
    }
    var toast=document.createElement('div');
    toast.className='app-toast toast-'+(type||'info');
    toast.innerHTML='<strong>'+String(type||'Notice').charAt(0).toUpperCase()+String(type||'notice').slice(1)+'</strong><span></span>';
    toast.querySelector('span').textContent=message;
    stack.appendChild(toast);
    setTimeout(function(){if(window.cssrMotion)window.cssrMotion.dismiss(toast);else toast.remove()},4200);
}
function refreshTableStates(){
    document.querySelectorAll('.table-responsive table').forEach(function(table){
        var tbody=table.querySelector('tbody');
        if(!tbody||tbody.querySelector('[data-empty-row]'))return;
        var colCount=table.querySelectorAll('thead th').length||1;
        var hasRows=Array.prototype.some.call(tbody.querySelectorAll('tr'),function(row){return row.children.length});
        if(!hasRows){
            var row=document.createElement('tr');
            row.setAttribute('data-empty-row','true');
            row.innerHTML='<td class="empty-state" colspan="'+colCount+'">No records to show yet.</td>';
            tbody.appendChild(row);
        }
    });
}
function enhanceTables(){
    document.querySelectorAll('.table-responsive table.table').forEach(function(table,index){
        if(table.dataset.enhanced==='true'||table.closest('.pos-receipt')||table.closest('.request-modal')||table.closest('.item-masterlist-scroll')||table.closest('.cssr-report-document'))return;
        table.dataset.enhanced='true';
        var rows=Array.prototype.slice.call(table.querySelectorAll('tbody tr')).filter(function(row){return !row.hasAttribute('data-empty-row')});
        if(rows.length<1)return;
        var responsive=table.closest('.table-responsive');
        var panel=responsive&&responsive.parentElement;
        var toolbar=document.createElement('div');
        toolbar.className='data-toolbar no-print';
        var defaultSize=table.dataset.pageSize||'25';
        toolbar.innerHTML='<div class="data-search"><span>Search</span><input class="form-control" type="search" placeholder="Search records"></div><div class="data-tools"><label>Rows <select class="form-select"><option value="10">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label></div>';
        if(panel)panel.insertBefore(toolbar,responsive);
        var search=toolbar.querySelector('input');
        var sizeSelect=toolbar.querySelector('select');
        if(Array.prototype.some.call(sizeSelect.options,function(option){return option.value===defaultSize})){sizeSelect.value=defaultSize}
        var pager=document.createElement('div');
        pager.className='data-pager no-print';
        pager.innerHTML='<span></span><div><button class="btn btn-light btn-sm" type="button">Previous</button><button class="btn btn-light btn-sm" type="button">Next</button></div>';
        if(panel)panel.insertBefore(pager,responsive.nextSibling);
        var info=pager.querySelector('span');
        var prev=pager.querySelectorAll('button')[0];
        var next=pager.querySelectorAll('button')[1];
        var page=1;
        function render(){
            var q=search.value.toLowerCase().trim();
            var filtered=rows.filter(function(row){return row.textContent.toLowerCase().includes(q)});
            var perPage=Number(sizeSelect.value)||25;
            var pages=Math.max(1,Math.ceil(filtered.length/perPage));
            page=Math.min(page,pages);
            rows.forEach(function(row){row.style.display='none'});
            filtered.slice((page-1)*perPage,page*perPage).forEach(function(row){row.style.display=''});
            info.textContent=filtered.length ? ('Showing '+(((page-1)*perPage)+1)+'-'+Math.min(page*perPage,filtered.length)+' of '+filtered.length+' records') : 'No matching records';
            prev.disabled=page<=1;
            next.disabled=page>=pages;
        }
        search.addEventListener('input',function(){page=1;render()});
        sizeSelect.addEventListener('change',function(){page=1;render()});
        prev.addEventListener('click',function(){page--;render()});
        next.addEventListener('click',function(){page++;render()});
        render();
    });
}
function startLoading(message){
    var overlay=document.getElementById('loadingOverlay');
    if(!overlay)return;
    overlay.querySelector('span').textContent=message||'Processing request...';
    overlay.classList.add('open');
}
document.addEventListener('DOMContentLoaded',function(){
    startLiveClock();
    var body=document.body;
    document.querySelectorAll('[data-ledger-combobox]').forEach(function(searchInput){
        var select=document.getElementById(searchInput.getAttribute('data-ledger-combobox'));
        if(!select)return;
        var wrapper=searchInput.closest('.ledger-combobox');
        var list=wrapper&&wrapper.querySelector('[data-ledger-options]');
        if(!list)return;
        var options=Array.from(select.options).filter(function(option){return option.value;}).map(function(option){return {value:option.value,text:option.text};});
        var selected=options.find(function(option){return option.value===select.value;});
        if(selected)searchInput.value=selected.text;
        function renderOptions(){
            var query=searchInput.value.trim().toLowerCase();
            var matches=options.filter(function(option){return !query||option.text.toLowerCase().indexOf(query)!==-1;});
            list.innerHTML='';
            matches.forEach(function(optionData){
                var button=document.createElement('button');
                button.type='button';
                button.textContent=optionData.text;
                button.addEventListener('mousedown',function(event){
                    event.preventDefault();
                    select.value=optionData.value;
                    searchInput.value=optionData.text;
                    searchInput.setCustomValidity('');
                    list.classList.remove('open');
                });
                list.appendChild(button);
            });
            if(!matches.length){
                var empty=document.createElement('div');
                empty.className='ledger-combobox-empty';
                empty.textContent='No matching item';
                list.appendChild(empty);
            }
            list.classList.remove('open-up');
            list.classList.add('open');
            var inputRect=searchInput.getBoundingClientRect();
            var availableBelow=window.innerHeight-inputRect.bottom-12;
            var availableAbove=inputRect.top-12;
            if(availableBelow<Math.min(280,list.scrollHeight)&&availableAbove>availableBelow){
                list.classList.add('open-up');
            }
        }
        searchInput.addEventListener('focus',renderOptions);
        searchInput.addEventListener('input',function(){select.value='';renderOptions();});
        searchInput.addEventListener('keydown',function(event){if(event.key==='Escape')list.classList.remove('open');});
        searchInput.addEventListener('blur',function(){setTimeout(function(){list.classList.remove('open');},120);});
        var form=searchInput.closest('form');
        if(form)form.addEventListener('submit',function(event){
            if(select.value)return;
            event.preventDefault();
            searchInput.setCustomValidity('Select an item from the list.');
            searchInput.reportValidity();
        });
        searchInput.addEventListener('input',function(){searchInput.setCustomValidity('');});
    });
    var sidebarStorageKey='cssrSidebarCollapsed';
    if(window.localStorage&&localStorage.getItem(sidebarStorageKey)==='1'){
        body.classList.add('sidebar-collapsed');
    }
    function setSidebarCollapsed(collapsed){
        body.classList.toggle('sidebar-collapsed',collapsed);
        if(window.localStorage)localStorage.setItem(sidebarStorageKey,collapsed?'1':'0');
        document.querySelectorAll('[data-sidebar-collapse]').forEach(function(button){
            button.setAttribute('aria-label',collapsed?'Expand navigation':'Collapse navigation');
            button.setAttribute('title',collapsed?'Expand sidebar':'Collapse sidebar');
        });
    }
    setSidebarCollapsed(body.classList.contains('sidebar-collapsed'));
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function(button){
        button.addEventListener('click',function(){
            if(window.matchMedia('(max-width: 900px)').matches){
                body.classList.add('sidebar-open');
                return;
            }
            setSidebarCollapsed(!body.classList.contains('sidebar-collapsed'));
        });
    });
    document.querySelectorAll('[data-sidebar-collapse]').forEach(function(button){
        button.addEventListener('click',function(){
            setSidebarCollapsed(!body.classList.contains('sidebar-collapsed'));
        });
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(function(button){button.addEventListener('click',function(){body.classList.remove('sidebar-open')})});
    document.addEventListener('keydown',function(event){
        if(event.key!=='Escape')return;
        var handled=false;
        var confirmModal=document.getElementById('confirmModal');
        if(confirmModal&&confirmModal.classList.contains('open')){
            var cancel=confirmModal.querySelector('[data-confirm-cancel]');
            if(cancel)cancel.click();else confirmModal.classList.remove('open');
            handled=true;
        }else{
            var imageModal=document.querySelector('#imageModal.open,.modal-backdrop.open');
            if(imageModal){
                var imageClose=imageModal.querySelector('[data-image-modal-close],[data-modal-close]');
                if(imageClose)imageClose.click();else imageModal.classList.remove('open');
                handled=true;
            }
        }
        if(!handled){
            var appModals=document.querySelectorAll('.app-modal.open');
            var appModal=appModals.length?appModals[appModals.length-1]:null;
            if(appModal){
                var appClose=appModal.querySelector('[data-modal-close]');
                if(appClose)appClose.click();else appModal.classList.remove('open');
                handled=true;
            }
        }
        if(!handled){
            var requestModal=document.querySelector('.request-modal.open');
            if(requestModal){
                if(window.parent&&window.parent!==window&&typeof window.parent.closeRequestHistoryDetail==='function'){
                    window.parent.closeRequestHistoryDetail();
                }else{
                    var requestClose=Array.prototype.find.call(requestModal.querySelectorAll('.request-modal-actions a,.request-modal-actions button'),function(control){return control.textContent.trim().toLowerCase()==='close'});
                    if(requestClose)requestClose.click();else requestModal.classList.remove('open');
                }
                handled=true;
            }
        }
        if(!handled){
            var bootstrapModal=document.querySelector('.modal.show');
            if(bootstrapModal){
                if(window.bootstrap&&bootstrap.Modal)bootstrap.Modal.getOrCreateInstance(bootstrapModal).hide();
                else bootstrapModal.classList.remove('show');
                handled=true;
            }
        }
        if(!handled&&body.classList.contains('sidebar-open')){
            body.classList.remove('sidebar-open');
            handled=true;
        }
        if(handled){event.preventDefault();event.stopImmediatePropagation()}
    },true);
    refreshTableStates();
    enhanceTables();
    document.querySelectorAll('[data-modal-open]').forEach(function(button){
        button.addEventListener('click',function(){var modal=document.querySelector(button.dataset.modalOpen);if(modal)modal.classList.add('open')});
    });
    document.querySelectorAll('[data-modal-close]').forEach(function(button){
        button.addEventListener('click',function(){var modal=button.closest('.app-modal');if(modal)modal.classList.remove('open')});
    });
});
document.addEventListener('click',function(event){
    var link=event.target.closest('a[onclick*="confirm("]');
    if(!link)return;
    var onclick=link.getAttribute('onclick')||'';
    var match=onclick.match(/confirm\(['"](.+?)['"]\)/);
    var message=match?match[1]:'Are you sure you want to continue?';
    event.preventDefault();
    showConfirm(message,function(){window.location.href=link.href});
},true);
document.addEventListener('submit',function(event){
    if(event.defaultPrevented)return;
    var form=event.target;
    if(!form||form.tagName!=='FORM')return;
    if((form.method||'').toLowerCase()!=='post')return;
    if(form.dataset.confirm==='off'){startLoading(form.dataset.loadingMessage);return}
    if(form.dataset.confirm==='custom')return;
    var button=event.submitter;
    var label=(button&&(button.dataset.confirmMessage||button.textContent||button.value))||'submit this form';
    var message=form.dataset.confirmMessage||('Are you sure you want to '+String(label).trim()+'?');
    event.preventDefault();
    showConfirm(message,function(){
        if(button&&button.name){
            var mirror=document.createElement('input');
            mirror.type='hidden';
            mirror.name=button.name;
            mirror.value=button.value||button.textContent||'1';
            form.appendChild(mirror);
        }
        form.dataset.confirm='off';
        startLoading(form.dataset.loadingMessage);
        form.submit();
    });
});

/* Use the phone's real visible viewport; desktop device emulation does not
   reproduce Safari/Chrome address-bar and on-screen-keyboard resizing. */
(function syncVisibleMobileViewport(){
    var root=document.documentElement;
    var update=function(){
        var viewport=window.visualViewport;
        var width=viewport?viewport.width:window.innerWidth;
        var height=viewport?viewport.height:window.innerHeight;
        var offsetTop=viewport?viewport.offsetTop:0;
        root.style.setProperty('--app-visual-width',Math.round(width)+'px');
        root.style.setProperty('--app-visual-height',Math.round(height)+'px');
        root.style.setProperty('--app-visual-top',Math.round(offsetTop)+'px');
    };
    update();
    window.addEventListener('resize',update,{passive:true});
    window.addEventListener('orientationchange',update,{passive:true});
    if(window.visualViewport){
        window.visualViewport.addEventListener('resize',update,{passive:true});
        window.visualViewport.addEventListener('scroll',update,{passive:true});
    }
})();

/* Safari can retain a fixed control inside its original layout position after
   the address bar resizes. Move mobile cart controls to body and enforce their
   viewport anchoring after page restore, rotation, and viewport changes. */
(function pinMobileCartBars(){
    var pin=function(){
        var viewport=window.visualViewport;
        var isIOS=/iPad|iPhone|iPod/.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
        var viewportBottomInset=0;
        if(isIOS&&viewport){
            var layoutHeight=Math.max(window.innerHeight||0,document.documentElement.clientHeight||0);
            viewportBottomInset=Math.max(0,Math.round(layoutHeight-(viewport.offsetTop+viewport.height)));
        }
        document.querySelectorAll('.mobile-cart-bar').forEach(function(bar){
            if(bar.parentElement!==document.body)document.body.appendChild(bar);
            if(!window.matchMedia('(max-width: 650px)').matches){
                bar.style.removeProperty('position');
                bar.style.removeProperty('top');
                bar.style.removeProperty('bottom');
                bar.style.removeProperty('left');
                bar.style.removeProperty('right');
                return;
            }
            bar.style.setProperty('position','fixed','important');
            bar.style.setProperty('top','auto','important');
            bar.style.setProperty('bottom',(viewportBottomInset+8)+'px','important');
            bar.style.setProperty('left','8px','important');
            bar.style.setProperty('right','8px','important');
            bar.style.setProperty('width','auto','important');
            bar.style.setProperty('margin','0','important');
            bar.style.setProperty('transform','translate3d(0,0,0)','important');
        });
    };
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',pin);else pin();
    window.addEventListener('pageshow',pin,{passive:true});
    window.addEventListener('resize',pin,{passive:true});
    window.addEventListener('orientationchange',pin,{passive:true});
    if(window.visualViewport){
        window.visualViewport.addEventListener('resize',pin,{passive:true});
        window.visualViewport.addEventListener('scroll',pin,{passive:true});
    }
})();

/* Lightweight connected particles for the JVGH system background. */
(function initJVGHParticles(){
    var canvas=document.getElementById('jvghParticleCanvas');
    if(!canvas||!canvas.getContext)return;
    var context=canvas.getContext('2d');
    var reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var particles=[];
    var width=0;
    var height=0;
    var frame=0;
    var pixelRatio=Math.min(window.devicePixelRatio||1,2);

    function resize(){
        width=window.innerWidth;
        height=window.innerHeight;
        canvas.width=Math.round(width*pixelRatio);
        canvas.height=Math.round(height*pixelRatio);
        canvas.style.width=width+'px';
        canvas.style.height=height+'px';
        context.setTransform(pixelRatio,0,0,pixelRatio,0,0);
        var desired=Math.max(18,Math.min(58,Math.round((width*height)/26000)));
        particles=Array.from({length:desired},function(){
            return{x:Math.random()*width,y:Math.random()*height,vx:(Math.random()-.5)*.22,vy:(Math.random()-.5)*.22,r:1+Math.random()*1.5};
        });
    }

    function draw(){
        context.clearRect(0,0,width,height);
        particles.forEach(function(p,index){
            if(!reduced){
                p.x+=p.vx;p.y+=p.vy;
                if(p.x<0||p.x>width)p.vx*=-1;
                if(p.y<0||p.y>height)p.vy*=-1;
            }
            context.beginPath();
            context.arc(p.x,p.y,p.r,0,Math.PI*2);
            context.fillStyle='rgba(50,140,193,.32)';
            context.fill();
            for(var next=index+1;next<particles.length;next++){
                var q=particles[next];
                var dx=p.x-q.x,dy=p.y-q.y,distance=Math.sqrt(dx*dx+dy*dy);
                if(distance<115){
                    context.beginPath();
                    context.moveTo(p.x,p.y);context.lineTo(q.x,q.y);
                    context.strokeStyle='rgba(11,60,93,'+((1-distance/115)*.09)+')';
                    context.lineWidth=.7;context.stroke();
                }
            }
        });
        if(!reduced)frame=requestAnimationFrame(draw);
    }

    resize();
    draw();
    var timer;
    window.addEventListener('resize',function(){
        clearTimeout(timer);timer=setTimeout(function(){cancelAnimationFrame(frame);resize();draw();},140);
    },{passive:true});
})();
