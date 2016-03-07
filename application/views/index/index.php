<style>
	.catalogue {
		text-align: center;
	}

	.catalogue .item {
		display: inline-block;
		border: 1px solid black;
		border-radius: 5px;
		width: 200px;
		height: 300px;
		text-align: center;
		margin: 30px;
	}

	.catalogue .item .name {
		
	}

	.catalogue .item .photo {
		height: 200px;
		width: 180px;
		border-radius: 5px;
		margin: 0 10px;
		background-size: cover;
		background-position: center;
	}

	.catalogue .item .price b {
		font-size: 18px;
	}
</style>

Итак, здесь мне нужны фильтры

<div class="catalogue">
	<?php
		for ($i = 0; $i < count($catalogue_items); $i++) {
	?>
		<div class="item">
				<a href="/girl/<?= $catalogue_items[$i]->id ?>"><H3 class="name"><?= $catalogue_items[$i]->name ?></H3></a>
				<div class="photo" style="background-image: url('<?= $catalogue_items[$i]->main_photo_url ?>');"></div>
				<div class="age">Возраст: <?= $catalogue_items[$i]->age ?></div>
				<div class="price">Цена за час: <b><?= $catalogue_items[$i]->price_for_hour ?>р.</b></div>
			</div>
	<?php
		}
	?>

</div>