<!DOCTYPE html>
<html lang="ru">
<head>
	<meta charset="<?=app::get()->config->encode?>">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="">
	<meta name="author" content="">
	<title><?=app::get()->config->sitename?></title>
	<link rel="icon" href="/assets/images/favicon.ico">
</head>
<body>

    <div class="blog-masthead">
      <div class="container">
        <nav class="blog-nav">
          <a class="blog-nav-item <?=app::get()->uri->controller=='index' ? 'active' :''?>" href="/">Главная</a>
          <a class="blog-nav-item <?=app::get()->uri->controller=='user' ? 'active' :''?>" href="/user/">Войти</a>
          <a class="blog-nav-item <?=app::get()->uri->controller=='page' ? 'active' :''?>" href="/about.html">О фреймворке</a>
          <a class="blog-nav-item <?=app::get()->uri->controller=='post' ? 'active' :''?>" href="/post">Материалы</a>
        </nav>
      </div>
    </div>

    <div class="container">
		<?= $content ?>
    </div><!-- /.container -->

    <footer class="blog-footer">
		<p><a href="#">Наверх</a></p>
    </footer>
	<?
	$this->addStyleSheet('/assets/css/blog.css','body');
	?>
  </body>
</html>