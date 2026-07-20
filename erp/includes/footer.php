    </main>
  </div><!-- /.main -->
</div><!-- /.app -->

<script>
  // ---- ডার্ক / লাইট মোড ----
  const html = document.documentElement;
  const tt = document.getElementById('theme-toggle');
  if (localStorage.getItem('theme') === 'dark' ||
     (!localStorage.getItem('theme') && matchMedia('(prefers-color-scheme: dark)').matches)) {
    html.setAttribute('data-theme','dark');
  }
  function syncIcon(){ tt.querySelector('i').className = html.getAttribute('data-theme')==='dark' ? 'bi bi-sun' : 'bi bi-moon-stars'; }
  syncIcon();
  tt?.addEventListener('click', () => {
    const dark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', dark ? 'light' : 'dark');
    localStorage.setItem('theme', dark ? 'light' : 'dark');
    syncIcon();
  });

  // ---- মোবাইল সাইডবার ----
  document.getElementById('menu-toggle')?.addEventListener('click', () =>
    document.getElementById('sidebar').classList.toggle('open'));

  // ---- ইউজার ড্রপডাউন ----
  const chip = document.getElementById('user-chip');
  const dd = document.getElementById('user-dropdown');
  chip?.addEventListener('click', (e) => { e.stopPropagation(); dd.classList.toggle('open'); });
  document.addEventListener('click', () => dd?.classList.remove('open'));

  // ---- সাধারণ মডাল হেল্পার ----
  function openModal(id){ document.getElementById(id)?.classList.add('open'); }
  function closeModal(id){ document.getElementById(id)?.classList.remove('open'); }
  document.querySelectorAll('.modal-c').forEach(m =>
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); }));
</script>
</body>
</html>
