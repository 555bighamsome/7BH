/* Researcher navigation only; the participant editor and teaching are unchanged. */
window.ShufflePreview = {install(){
  const config=window.SHUFFLE_PREVIEW,pool=window.SHARED_POOL;
  if(!config.preview)return;
  if(config.inspect)scheduleAutoAdvance=()=>{};
  const sequence=pool.sequences[config.schedule];
  const launcher=document.createElement('button');
  launcher.id='shuffle-test';launcher.type='button';launcher.className='test-condition-launcher';
  launcher.textContent='Test';launcher.setAttribute('aria-haspopup','dialog');launcher.setAttribute('aria-expanded','false');
  const backdrop=document.createElement('div');
  backdrop.id='shuffle-test-backdrop';backdrop.className='test-condition-backdrop';backdrop.hidden=true;
  backdrop.innerHTML=`<section class="test-condition-panel" role="dialog" aria-modal="true" aria-labelledby="shuffle-test-title">
    <header class="test-condition-header"><div><strong id="shuffle-test-title">Shared task pool</strong>
      <span>Local preview. 5 expansion tasks + the same 6 reordered tasks.</span></div>
      <button class="test-condition-close" type="button" aria-label="Close">&times;</button></header>
    <div class="test-condition-matrix"><span></span><strong class="test-condition-column">Local continuation</strong>
      ${Object.entries(pool.sequences).map(([id,s])=>`<strong class="test-condition-row">${s.title}</strong>
        <button type="button" class="test-condition-cell${id===config.schedule?' is-current':''}" data-schedule="${id}">${id===config.schedule?'Current':'Start T1'}</button>`).join('')}
    </div>
    <p id="shuffle-order"></p><p class="shuffle-note">Positions above refer to the six tasks after expansion. E is measured separately.</p>
    <div class="shuffle-inspection">
      <label for="shuffle-trial">Inspect trial</label><select id="shuffle-trial"></select>
      <button type="button" class="btn" id="shuffle-inspect">Open</button>
      <button type="button" class="btn" id="shuffle-critical">Inspect ${sequence.probe_position?'critical task':'first post-expansion task'}</button>
    </div>
    <p id="shuffle-reference" class="shuffle-note"></p>
    <div class="shuffle-links"><button type="button" class="btn" id="shuffle-reference-load">Load reference carried rule</button>
      <a href="../review.html">Material audit</a></div>
    <p class="shuffle-note">Sequence mode carries your actual rule. Inspection entry loads the reference carried rule. Test actions are not participant data.</p>
  </section>`;
  const trial=backdrop.querySelector('#shuffle-trial');
  sequence.materials.forEach((name,i)=>trial.add(new Option(`T${i+1} (${name})`,String(i+1))));
  const navigate=(schedule,mode='sequence',trialNumber=1)=>{
    const url=new URL(location.href);
    url.search=new URLSearchParams({schedule,continuation:'local',mode,trial:String(trialNumber),preview:'1',skipTutorial:'1',v:pool.version});
    location.href=url.href;
  };
  const close=()=>{backdrop.hidden=true;launcher.setAttribute('aria-expanded','false');launcher.focus();};
  launcher.onclick=()=>{
    trial.value=String(curIndex+1);
    backdrop.querySelector('#shuffle-order').textContent=`${sequence.title}: ${sequence.order.join(' > ')}`;
    const reference=sequence.reference[curIndex];
    backdrop.querySelector('#shuffle-reference').textContent=`T${curIndex+1} / ${scn.itemId}: reference E=${reference.E}, D=${reference.D}. ${config.inspect?'Inspection':'Sequence'} mode.`;
    backdrop.hidden=false;launcher.setAttribute('aria-expanded','true');
    backdrop.querySelector('.test-condition-cell.is-current').focus();
  };
  backdrop.querySelector('.test-condition-close').onclick=close;
  backdrop.onclick=e=>{if(e.target===backdrop)close();};
  backdrop.querySelectorAll('.test-condition-cell').forEach(b=>b.onclick=()=>navigate(b.dataset.schedule));
  backdrop.querySelector('#shuffle-inspect').onclick=()=>navigate(config.schedule,'inspect',Number(trial.value));
  backdrop.querySelector('#shuffle-critical').onclick=()=>navigate(config.schedule,'inspect',sequence.probe_trial);
  backdrop.querySelector('#shuffle-reference-load').onclick=()=>{
    rules=starterRulesFor(scn);
    localCompletedByTask.delete(scn.id);resetRunAfterRuleEdit();renderRules();
    recordRuleEvent('researcher_reference_loaded',{material_id:scn.itemId});close();
  };
  document.addEventListener('keydown',event=>{
    if(backdrop.hidden)return;
    if(event.key==='Escape')close();
    if(event.key==='Tab'){
      const nodes=[...backdrop.querySelectorAll('button,select,a')].filter(n=>!n.disabled);
      const first=nodes[0],last=nodes.at(-1);
      if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
      else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
    }
  });
  const originalPlay=play;
  play=function(trigger='manual'){
    const before=runs.length;
    originalPlay(trigger);
    if(runs.length>before)runs.at(-1).preview_context={researcher_only:true,material_sha256:pool.material_sha256,
      schedule:config.schedule,mode:config.inspect?'inspect':'sequence',material_id:scn.itemId,trial:curIndex+1};
  };
  document.body.append(launcher,backdrop);
}};
