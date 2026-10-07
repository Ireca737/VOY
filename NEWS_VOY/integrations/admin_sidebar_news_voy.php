<?php
/*
 * NEWS VOY - integrazione sidebar amministrativa.
 * Mantiene la stessa permission 3 usata dal precedente menu News.
 */
if (userHasPermission(3)) { ?>
    <a class="nav-link" href="/admin/news_voy/index.php">
        <div class="nav-link-icon"><i data-feather="align-left"></i></div>
        News VOY
    </a>
<?php } ?>
