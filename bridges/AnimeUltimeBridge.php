<?php
class AnimeUltimeBridge extends BridgeAbstract {

    private $filter = 'Releases';

    public function loadMetadatas() {

        $this->maintainer = 'ORelio';
        $this->name = 'Anime-Ultime';
        $this->uri = 'http://www.anime-ultime.net/';
        $this->description = 'Returns the 10 newest releases posted on Anime-Ultime';
        $this->update = '2016-08-17';

        $this->parameters[] =
        '[
            {
                "name" : "Type",
                "type" : "list",
                "identifier" : "type",
                "values" :
                [
                    {
                        "name" : "Everything",
                        "value" : ""

                    },
                    {
                        "name" : "Anime",
                        "value" : "A"

                    },
                    {
                        "name" : "Drama",
                        "value" : "D"
                    },
                    {
                        "name" : "Tokusatsu",
                        "value" : "T"

                    }
                ]
            }
        ]';
    }

    public function collectData(array $param) {

        //Add type filter if provided
        $typeFilter = '';
        if (!empty($param['type'])) {
            if ($param['type'] == 'A' || $param['type'] == 'D' || $param['type'] == 'T') {
                $typeFilter = $param['type'];
                if ($typeFilter == 'A') { $this->filter = 'Anime'; }
                if ($typeFilter == 'D') { $this->filter = 'Drama'; }
                if ($typeFilter == 'T') { $this->filter = 'Tokusatsu'; }
            } else $this->returnClientError('The provided type filter is invalid. Expecting A, D, T, or no filter');
        }

        //Build date and filters for making requests
        $thismonth = date('mY').$typeFilter;
        $lastmonth = date('mY', mktime(0, 0, 0, date('n') - 1, 1, date('Y'))).$typeFilter;

        //Process each HTML page until having 10 releases
        $processedOK = 0;
        foreach (array($thismonth, $lastmonth) as $requestFilter) {

            //Retrive page contents
            $website = 'http://www.anime-ultime.net/';
            $url = $website.'history-0-1/'.$requestFilter;
            $html = $this->file_get_html($url) or $this->returnServerError('Could not request Anime-Ultime: '.$url);

            //Relases are sorted by day : process each day individually
            foreach ($html->find('div.history', 0)->find('h3') as $daySection) {

                //Retrieve day and build date information
                $dateString = $daySection->plaintext;
                $day = intval(substr($dateString, strpos($dateString, ' ') + 1, 2));
                $item_date = strtotime(str_pad($day, 2, '0', STR_PAD_LEFT).'-'.substr($requestFilter, 0, 2).'-'.substr($requestFilter, 2, 4));
                $release = $daySection->next_sibling()->next_sibling()->first_child(); //<h3>day</h3><br /><table><tr> <-- useful data in table rows

                //Process each release of that day, ignoring first table row: contains table headers
                while (!is_null($release = $release->next_sibling())) {
                    if (count($release->find('td')) > 0) {

                        //Retrieve metadata from table columns
                        $item_link_element = $release->find('td', 0)->find('a', 0);
                        $item_uri = $website.$item_link_element->href;
                        $item_name = html_entity_decode($item_link_element->plaintext);
                        $item_episode = html_entity_decode(str_pad($release->find('td', 1)->plaintext, 2, '0', STR_PAD_LEFT));
                        $item_fansub = $release->find('td', 2)->plaintext;
                        $item_type = $release->find('td', 4)->plaintext;

                        if (!empty($item_uri)) {

                            //Retrieve description from description page and convert relative image src info absolute image src
                            $html_item = file_get_contents($item_uri) or $this->returnServerError('Could not request Anime-Ultime: '.$item_uri);
                            $item_description = substr($html_item, strpos($html_item, 'class="principal_contain" align="center">') + 41);
                            $item_description = substr($item_description, 0, strpos($item_description, '<div id="table">'));
                            $item_description = str_replace('src="images', 'src="'.$website.'images', $item_description);
                            $item_description = str_replace("\r", '', $item_description);
                            $item_description = str_replace("\n", '', $item_description);
                            $item_description = utf8_encode($item_description);

                            //Build and add final item
                            $item = new \Item();
                            $item->uri = $item_uri;
                            $item->title = $item_name.' '.$item_type.' '.$item_episode;
                            $item->author = $item_fansub;
                            $item->timestamp = $item_date;
                            $item->content = $item_description;
                            $this->items[] = $item;
                            $processedOK++;
                            
                            //Stop processing once limit is reached
                            if ($processedOK >= 10)
                                return;
                        }
                    }
                }
            }
        }
    }

    public function getName() {
        return 'Latest '.$this->filter.' - Anime-Ultime Bridge';
    }

    public function getCacheDuration() {
        return 3600*3; // 3 hours
    }

}
