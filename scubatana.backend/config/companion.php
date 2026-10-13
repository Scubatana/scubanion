<?php

return [

	'assets_path' => resource_path('companion'),

	'categories' => [
		'hat' => ['label' => 'Chapeau', 'origin' => ['x' => 50, 'y' => 75]],
		'glasses' => ['label' => 'Yeux', 'origin' => ['x' => 50, 'y' => 50]],
		'moustache' => ['label' => 'Moustache', 'origin' => ['x' => 50, 'y' => 50]],
		'mouth' => ['label' => 'Bouche', 'origin' => ['x' => 30, 'y' => 80]],
		'neck' => ['label' => 'Cou', 'origin' => ['x' => 50, 'y' => 50]],
	],

	'species' => [
		'scuba' => [
			'label' => 'Scuba',
			'image' => 'species/scuba.svg',
			'anchors' => [
				'hat' => ['x' => 50, 'y' => 18, 'width' => 56],
				'glasses' => ['x' => 50, 'y' => 49.5, 'width' => 44],
				'moustache' => ['x' => 50, 'y' => 57, 'width' => 32],
				'mouth' => ['x' => 50, 'y' => 59.5, 'width' => 64],
				'neck' => ['x' => 50, 'y' => 75, 'width' => 40],
			],
		],
		'dog' => [
			'label' => 'Chien',
			'image' => 'species/dog.svg',
			'anchors' => [
				'hat' => ['x' => 50, 'y' => 18, 'width' => 56],
				'glasses' => ['x' => 50, 'y' => 49.5, 'width' => 44],
				'moustache' => ['x' => 50, 'y' => 60, 'width' => 32],
				'mouth' => ['x' => 50, 'y' => 59.5, 'width' => 64],
				'neck' => ['x' => 50, 'y' => 75, 'width' => 40],
			],
		],
		'cat' => [
			'label' => 'Chat',
			'image' => 'species/cat.svg',
			'anchors' => [
				'hat' => ['x' => 50, 'y' => 18, 'width' => 56],
				'glasses' => ['x' => 50, 'y' => 49.5, 'width' => 44],
				'moustache' => ['x' => 50, 'y' => 59, 'width' => 32],
				'mouth' => ['x' => 50, 'y' => 59.5, 'width' => 64],
				'neck' => ['x' => 50, 'y' => 75, 'width' => 40],
			],
		],
		'axolotl' => [
			'label' => 'Axolotl',
			'image' => 'species/axolotl.svg',
			'anchors' => [
				'hat' => ['x' => 50, 'y' => 18, 'width' => 56],
				'glasses' => ['x' => 50, 'y' => 49.5, 'width' => 44],
				'moustache' => ['x' => 50, 'y' => 57, 'width' => 32],
				'mouth' => ['x' => 50, 'y' => 60.5, 'width' => 64],
				'neck' => ['x' => 50, 'y' => 75, 'width' => 40],
			],
		],
	],

	'colors' => [
		'ocean' => ['label' => 'Océan', 'main' => '#7FB7C4', 'shade' => '#4E8A99', 'light' => '#EAF6F8'],
		'sand' => ['label' => 'Sable', 'main' => '#E6BE8A', 'shade' => '#BA8648', 'light' => '#FBF1E2'],
		'coral' => ['label' => 'Corail', 'main' => '#F3A183', 'shade' => '#D96A41', 'light' => '#FDEDE6'],
		'sun' => ['label' => 'Soleil', 'main' => '#F2CF6B', 'shade' => '#D9A62E', 'light' => '#FFF6DA'],
		'mint' => ['label' => 'Menthe', 'main' => '#8ED6A5', 'shade' => '#4FA871', 'light' => '#E9F8EE'],
		'lagoon' => ['label' => 'Lagon', 'main' => '#6FCFDC', 'shade' => '#2F9BAD', 'light' => '#E3F7FA'],
		'violet' => ['label' => 'Violet', 'main' => '#B3A1E6', 'shade' => '#7C63C9', 'light' => '#F0ECFB'],
		'rose' => ['label' => 'Rose', 'main' => '#F0A3C8', 'shade' => '#D2649A', 'light' => '#FDECF4'],
	],

	'accessories' => [
		'tophat' => ['label' => 'Haut-de-forme', 'category' => 'hat', 'image' => 'accessories/tophat.svg'],
		'crown' => ['label' => 'Couronne', 'category' => 'hat', 'image' => 'accessories/crown.svg', 'earnable' => true],
		'cap' => ['label' => 'Casquette', 'category' => 'hat', 'image' => 'accessories/cap.svg'],
		'diving_mask' => ['label' => 'Masque de plongée', 'category' => 'glasses', 'image' => 'accessories/diving_mask.svg', 'earnable' => true],
		'sunglasses' => ['label' => 'Lunettes de soleil', 'category' => 'glasses', 'image' => 'accessories/sunglasses.svg'],
		'round_glasses' => ['label' => 'Lunettes rondes', 'category' => 'glasses', 'image' => 'accessories/round_glasses.svg'],
		'heart_glasses' => ['label' => 'Lunettes cœur', 'category' => 'glasses', 'image' => 'accessories/heart_glasses.svg'],
		'handlebar' => ['label' => 'Moustache guidon', 'category' => 'moustache', 'image' => 'accessories/handlebar.svg'],
		'pencil' => ['label' => 'Moustache fine', 'category' => 'moustache', 'image' => 'accessories/pencil.svg'],
		'walrus' => ['label' => 'Moustache morse', 'category' => 'moustache', 'image' => 'accessories/walrus.svg'],
		'snorkel' => ['label' => 'Tuba', 'category' => 'mouth', 'image' => 'accessories/snorkel.svg'],
		'lollipop' => ['label' => 'Sucette', 'category' => 'mouth', 'image' => 'accessories/lollipop.svg'],
		'flower' => ['label' => 'Fleur', 'category' => 'mouth', 'image' => 'accessories/flower.svg'],
		'bowtie' => ['label' => 'Nœud papillon', 'category' => 'neck', 'image' => 'accessories/bowtie.svg'],
		'scarf' => ['label' => 'Écharpe', 'category' => 'neck', 'image' => 'accessories/scarf.svg'],
		'medal' => ['label' => 'Médaille', 'category' => 'neck', 'image' => 'accessories/medal.svg', 'earnable' => true],
	],

];
