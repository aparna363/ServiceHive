<?php


class UrbanSearch {
    private $services = [
        'cleaning' => [
            ['id' => 1, 'name' => 'House Cleaning', 'price' => '₹399', 'rating' => 4.8],
            ['id' => 2, 'name' => 'Kitchen Cleaning', 'price' => '₹499', 'rating' => 4.7],
            ['id' => 3, 'name' => 'Bathroom Cleaning', 'price' => '₹299', 'rating' => 4.9]
        ],
        'plumber' => [
            ['id' => 4, 'name' => 'Tap & Mixer', 'price' => '₹149', 'rating' => 4.6],
            ['id' => 5, 'name' => 'Basin & Sink', 'price' => '₹199', 'rating' => 4.8],
            ['id' => 6, 'name' => 'Toilet', 'price' => '₹299', 'rating' => 4.7]
        ],
        'electrician' => [
            ['id' => 7, 'name' => 'Switch & Socket', 'price' => '₹149', 'rating' => 4.8],
            ['id' => 8, 'name' => 'Fan', 'price' => '₹199', 'rating' => 4.7],
            ['id' => 9, 'name' => 'Light', 'price' => '₹99', 'rating' => 4.9]
        ]
    ];

    private $popular_searches = [
        'AC Service',
        'Cleaning Services',
        'Electrician',
        'Plumber',
        'Carpenter'
    ];

    public function search($term) {
        $results = [];
        $term = strtolower($term);

        foreach ($this->services as $category => $services) {
            foreach ($services as $service) {
                if (strpos(strtolower($service['name']), $term) !== false ||
                    strpos(strtolower($category), $term) !== false) {
                    $service['category'] = $category;
                    $results[] = $service;
                }
            }
        }

        return [
            'results' => $results,
            'popular_searches' => $this->popular_searches
        ];
    }
}

if (isset($_GET['term'])) {
    $search = new UrbanSearch();
    echo json_encode($search->search($_GET['term']));
}
?>