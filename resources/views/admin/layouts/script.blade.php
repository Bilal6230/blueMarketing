<script>
    $(function(){
        function applyTheme(theme){
            const mode=theme==='dark-mode'?'dark-mode':'light';
            $('body').removeClass('dark-mode light').addClass(mode);
            $('#icontheme').attr('class',mode==='dark-mode'?'fas fa-moon':'fas fa-sun');
        }
        applyTheme(localStorage.getItem('theme'));
        $('#btntheme').on('click',function(){
            const next=localStorage.getItem('theme')==='dark-mode'?'light':'dark-mode';
            localStorage.setItem('theme',next);
            applyTheme(next);
        });
        $('.datatable').DataTable({stateSave:true});
    });
</script>