(() => {
  const $ = (s,r=document)=>r.querySelector(s), $$=(s,r=document)=>[...r.querySelectorAll(s)];
  const state={page:1,busy:false,hasMore:true,mode:'for-you',category:'all'};
  const feed=$('#havenFeed'), sentinel=$('#feedSentinel'), toast=$('#havenToast');

  function esc(v){const d=document.createElement('div');d.textContent=v??'';return d.innerHTML;}
  function notify(msg){toast.textContent=msg;toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),2500);}
  async function api(url,opts={}){const r=await fetch(url,{credentials:'same-origin',...opts});const j=await r.json().catch(()=>({success:false,message:'Server returned invalid JSON'}));if(!r.ok||j.success===false)throw new Error(j.message||'Request failed');return j;}

  function postHTML(p){
    const reactions=[['support','🌿','Support'],['empathy','💚','Empathy'],['helpful','✨','Helpful'],['like','♥','Like']];
    const media=p.media_url?(p.media_type==='photo'?`<img class="post-media" src="${esc(p.media_url)}" alt="">`:p.media_type==='video'?`<video class="post-media" controls src="${esc(p.media_url)}"></video>`:`<audio controls src="${esc(p.media_url)}"></audio>`):'';
    const ai=p.ai_reply?`<div class="ai-reply"><b>MindGuide AI</b><p>${esc(p.ai_reply)}</p><small>AI support • Volunteer support may follow shortly</small></div>`:'';
    return `<article class="haven-post" data-id="${p.id}" data-user="${p.user_id}">
      <header class="post-head">
        <a class="author" href="profile.php?id=${p.user_id}"><span class="avatar" style="background:${esc(p.avatar_color)}">${p.is_anonymous?'H':esc((p.author_name||'H')[0])}</span><span><b>${esc(p.author_name)}</b><small>@${esc(p.username||'haven')} · ${new Date(p.created_at).toLocaleString()}</small></span></a>
        <button class="post-menu" data-action="menu" aria-label="Post menu">•••</button>
      </header>
      <div class="post-category">${esc(p.category||'General')} ${p.mood?`· ${esc(p.mood)}`:''}</div>
      <div class="post-content">${esc(p.content).replace(/\n/g,'<br>')}</div>
      ${media}
      ${ai}
      <div class="post-stats"><span>${p.reaction_count} reactions</span><span>${p.comment_count} comments</span><span>${p.share_count||0} shares</span></div>
      <div class="reaction-row">${reactions.map(x=>`<button class="react ${p.user_reaction===x[0]?'active':''}" data-action="react" data-type="${x[0]}">${x[1]} <span>${x[2]}</span></button>`).join('')}</div>
      <div class="post-actions">
        <button data-action="comments">💬 <span>Comments</span></button>
        <button data-action="save">${p.is_saved?'🔖 Saved':'🔖 Save'}</button>
        <button data-action="share">↗ Share</button>
      </div>
      <section class="comments" hidden>
        <div class="comment-list"></div>
        <form class="comment-form"><input name="content" maxlength="3000" placeholder="Write something supportive…"><button>Send</button></form>
      </section>
    </article>`;
  }

  async function load(reset=false){
    if(state.busy||(!state.hasMore&&!reset))return;
    state.busy=true;if(reset){state.page=1;state.hasMore=true;feed.innerHTML='';}
    try{
      const j=await api(`api/community/feed.php?page=${state.page}&limit=8&mode=${encodeURIComponent(state.mode)}&category=${encodeURIComponent(state.category)}`);
      if(reset)feed.innerHTML='';
      if(!j.posts.length&&state.page===1)feed.innerHTML='<div class="empty">No posts here yet. Be the first to share something gentle. 🌿</div>';
      feed.insertAdjacentHTML('beforeend',j.posts.map(postHTML).join(''));
      state.hasMore=j.has_more;state.page++;
    }catch(e){notify(e.message);}
    finally{state.busy=false;}
  }

  async function comments(post){
    const box=$('.comments',post), list=$('.comment-list',post);
    box.hidden=!box.hidden;if(box.hidden)return;
    try{const j=await api(`api/comments.php?post_id=${post.dataset.id}`);list.innerHTML=j.comments.map(c=>`<div class="comment"><span class="mini-avatar" style="background:${esc(c.avatar_color)}"></span><div><b>${esc(c.author_name)}</b><p>${esc(c.content)}</p><small>${new Date(c.created_at).toLocaleString()}</small></div></div>`).join('')||'<small>No comments yet. Be the first to offer support.</small>';}
    catch(e){notify(e.message);}
  }

  feed.addEventListener('click',async e=>{
    const btn=e.target.closest('[data-action]');if(!btn)return;
    const post=e.target.closest('.haven-post'), id=post?.dataset.id;if(!post)return;
    try{
      if(btn.dataset.action==='comments'){comments(post);return;}
      if(btn.dataset.action==='react'){const j=await api('api/reaction.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({post_id:id,reaction_type:btn.dataset.type,current_reaction:post.querySelector('.react.active')?.dataset.type||''})});post.querySelectorAll('.react').forEach(x=>x.classList.remove('active'));if(j.active)btn.classList.add('active');$('.post-stats',post).firstElementChild.textContent=`${j.reaction_count} reactions`;notify(j.active?'Your support was added.':'Reaction removed.');}
      if(btn.dataset.action==='save'){const j=await api('api/bookmark.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({post_id:id})});btn.textContent=j.saved?'🔖 Saved':'🔖 Save';notify(j.saved?'Saved for later.':'Removed from saved.');}
      if(btn.dataset.action==='share'){await navigator.clipboard?.writeText(location.origin+location.pathname+'?post='+id);notify('Post link copied.');}
      if(btn.dataset.action==='menu'){const choice=prompt('Type: hide, report, or delete');if(choice==='hide'){await api('api/hide-post.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({post_id:id})});post.remove();}else if(choice==='report'){await api('api/report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({post_id:id,reason:'Community report'})});notify('Report sent to moderation.')}else if(choice==='delete'){await api('api/community/delete-post.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({post_id:id})});post.remove();notify('Post deleted.');}}
    }catch(err){notify(err.message);}
  });
  feed.addEventListener('submit',async e=>{
    if(!e.target.matches('.comment-form'))return;e.preventDefault();const f=e.target,post=e.target.closest('.haven-post');
    try{const j=await api('api/comment.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({post_id:post.dataset.id,content:f.content.value})});f.reset();$('.comment-list',post).insertAdjacentHTML('beforeend',`<div class="comment"><b>You</b><p>${esc(j.comment.content)}</p></div>`);$('.post-stats',post).children[1].textContent=`${Number($('.post-stats',post).children[1].textContent.match(/\d+/)?.[0]||0)+1} comments`;notify('Comment posted.');}catch(err){notify(err.message);}
  });

  $$('.feed-tab').forEach(b=>b.addEventListener('click',()=>{$$('.feed-tab').forEach(x=>x.classList.remove('active'));b.classList.add('active');state.mode=b.dataset.mode;load(true);}));
  $$('.category-chip').forEach(b=>b.addEventListener('click',()=>{$$('.category-chip').forEach(x=>x.classList.remove('active'));b.classList.add('active');state.category=b.dataset.category;load(true);}));
  const io=new IntersectionObserver(es=>es.forEach(x=>x.isIntersecting&&load()),{rootMargin:'700px'});if(sentinel)io.observe(sentinel);

  // Create post
  const modal=$('#postModal'), form=$('#createPostForm');
  $('#openCreate')?.addEventListener('click',()=>modal.classList.add('open'));
  modal?.addEventListener('click',e=>{if(e.target===modal||e.target.closest('[data-close]'))modal.classList.remove('open')});
  form?.addEventListener('submit',async e=>{e.preventDefault();const b=$('button[type=submit]',form);b.disabled=true;b.textContent='Publishing…';try{const j=await api('api/community/create-post.php',{method:'POST',body:new FormData(form)});modal.classList.remove('open');form.reset();load(true);notify(j.message);}catch(err){notify(err.message);}finally{b.disabled=false;b.textContent='Publish';}});

  // AI
  const aiBox=$('#aiPanel'), aiInput=$('#aiInput'), aiLog=$('#aiLog');
  $('#aiFab')?.addEventListener('click',()=>aiBox.classList.toggle('open'));
  $$('.ai-role').forEach(b=>b.addEventListener('click',()=>{$$('.ai-role').forEach(x=>x.classList.remove('active'));b.classList.add('active');aiBox.dataset.role=b.dataset.role;}));
  $('#aiForm')?.addEventListener('submit',async e=>{e.preventDefault();const msg=aiInput.value.trim();if(!msg)return;const role=aiBox.dataset.role||'mindguide';aiLog.insertAdjacentHTML('beforeend',`<div class="ai-user">${esc(msg)}</div><div class="ai-thinking">Mind${role.replace('mind','')} is thinking…</div>`);aiInput.value='';try{const j=await api('api/ai.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:msg,role})});$('.ai-thinking',aiLog)?.remove();aiLog.insertAdjacentHTML('beforeend',`<div class="ai-message"><b>${role}</b><p>${esc(j.reply)}</p></div>`);aiLog.scrollTop=aiLog.scrollHeight;}catch(err){$('.ai-thinking',aiLog)?.remove();notify(err.message);}});
  load(true);
})();