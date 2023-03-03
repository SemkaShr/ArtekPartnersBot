<?php
define("CHECK_TIMEOUT", 20);
define("COLOR", "#006eb9");
define("SAVE_FILE", './save.json');
define("ARTEK_LINK", "https://artek.org/vuzy-partnery/partners/");
define("DISCORD_WEBHOOK", "https://discord.com/api/webhooks/0/0");

echo coloredString("------------------------------------\n\r", "light_cyan");
echo coloredString("[] Artek parters auto-find\n\r", "light_green");
echo coloredString("[] Date: 03.03.2023\n\r", "light_green");
echo coloredString("[] Author: Semyon Shirokov\n\r", "light_green");
echo coloredString("[] Other: \n\r[] -- Discord webhook: https://github.com/renzbobz/DiscordWebhook-PHP\n\r", "light_green");
echo coloredString("------------------------------------\n\r", "light_cyan");

echo coloredString("- Starting...\n\r", "green");

error_reporting( 0 );

require_once("webhook.php");
$dw = new DiscordWebhook(DISCORD_WEBHOOK);
$msg = $dw
  ->newMessage()
  ->setTitle("Скрипт запущен!")
  ->setTimestamp()
  ->setColor(COLOR)
  ->send();

echo coloredString("- Webhook added...\n\r", "green");

echo coloredString("------------------------------------\n\r", "light_cyan");

while(true){
    echo coloredString("{} Updating... ".date("Y-m-d H:i:s")."\n\r", "cyan");
    $result = partnerInformation();
    sleep(CHECK_TIMEOUT);
}

echo coloredString("------------------------------------\n\r", "light_cyan");
echo coloredString("- Script stopped.\n\r", "red");

function sendAlertPartners($array){
    global $dw;
    foreach($array as $key => $value){
        $title = $value['data']['title'];
        $event_am = $value['data']['events_amount'];
        $event_name = $value['data']['event_name'];

        $msg = $dw->newMessage()->setTimestamp()->setColor(COLOR)
                  ->addField("Партнер", $title, true)
                  ->addField("Название программы", $event_name, true);

        if($value['type'] == 'new_partner'){
            $msg = $msg->setTitle('Новый партнер', ARTEK_LINK);
        } else {
            $msg = $msg->setTitle('Новое положение', ARTEK_LINK);
        }

        if(count($value['data']['event_links']) > 0){
            $field_val = '';
            foreach($value['data']['event_links'] as $link_id => $link_value){
                $field_val .= '['.($link_id+1).'. '.$link_value['name'].']('.$link_value['href'].')'."\n";
            }
            $msg->addField("Конкурсные положения: ", $field_val, true);
        }

        if(count($value['data']['other_links']) > 0){
            $field_val = '';
            foreach($value['data']['other_links'] as $link_id => $link_value){
                $field_val .= '['.($link_id+1).'. '.$link_value['name'].']('.$link_value['href'].')'."\n";
            }
            $msg->addField("Другие ссылки: ", $field_val, true);
        }

        if($value['data']['avatar']){
            $msg->setThumbnail($value['data']['avatar'], $value['data']['avatar'], 150, 150);
            //$msg->setImage($value['data']['avatar'], $value['data']['avatar'], 100, 100);
        }

        echo coloredString('{}', 'cyan').coloredString(" Обновлено: {$title}, {$event_name}\n\r");

        $msg->send();
    }
}

function partnerInformation(){
    $doc = new DOMDocument();
    $doc->loadHTML(file_get_contents(ARTEK_LINK));

    $els = findByClass($doc, 'li', 'partner-list__item');
    $result = parsePartners($els);
    $last_result = json_decode(file_get_contents(SAVE_FILE), true) or array('events' => 0, 'data' => array());
    
    $change = $result['events'] - $last_result['events'];
    echo coloredString('{} ', 'cyan').coloredString('Finded: '.$result['events'].' events ('.($change>0 ? '>' : ($change<0 ? '<' : '==')).''.abs($change).')'."\n\r", 'green');

    if(file_exists(SAVE_FILE)){
        $exp = array();
        if($change > 0){
            foreach($result['data'] as $key => $value){
                $key = array_search($value['title'], array_column($last_result, 'title'));
                if($key){
                    if($value['events_amount'] != $last_result['data'][$key]['events_amount']){
                        $exp[] = array(
                            'type' => 'new_event',
                            'data' => $value
                        );
                    }
                } else {
                    $exp[] = array(
                        'type' => 'new_partner',
                        'data' => $value
                    );
                }
            }
            sendAlertPartners($exp);
        }
    }
    file_put_contents(SAVE_FILE, json_encode($result));
}

function findByClass($parent, $item, $class){
    $childs = $parent->getElementsByTagName($item);
    for ($i=0;$i<$childs->length;$i++){
        $temp = $childs->item($i);
        if (stripos($temp->getAttribute('class'), $class) !== false) {
            $els[] = $temp;
        }
    }
    return $els;
}

function parsePartners($partners){
    $find = 0;
    $data = array();
    foreach ($partners as $key => $value){
        $content = $value->textContent;
        if (strpos($content, "Положение") !== false){
            $partnerTitle = findByClass($value,'div','partner__title')[0]->textContent;
            $part = findByClass($value,'div','partner__right')[0];
            $partnerProgram = findByClass($part,'div','partner__about-desc')[0]->textContent;
            $am = substr_count($content, 'Положение');
            
            $links = array();
            $other_links = array();
            foreach(findByClass($value,'div','attachment__name') as $at_id => $at_name){
                $val = $at_name->firstChild->attributes->getNamedItem("href")->nodeValue;
                if(strpos($at_name->textContent, 'Положение') !== false){
                    $links[] = array('name' => $at_name->firstChild->textContent,'href' => $val);
                } else {
                    $other_links[] = array('name' => $at_name->firstChild->textContent, 'href' => $val);
                }
            }

            $avatar_parent = findByClass($value,'div','partner__logo')[0];
            $avatar = $avatar_parent->firstChild;
            if($avatar){
                $avatar_href = $avatar->attributes->getNamedItem("src")->nodeValue;
            }

            $data[] = array(
                'title' => $partnerTitle,
                'events_amount' => $am,
                'event_name' => $partnerProgram,
                'event_links' => $links,
                'other_links' => $other_links,
                'avatar' => $avatar_href
            );
            $find += $am;
        }
    }
    return array('events' => $find,'data' => $data);
}

function coloredString($string, $fColor=null, $bColor=null){
    $foreground_color = array();
    $background_color = array();
    
    $foreground_colors['black'] = '0;30';
    $foreground_colors['dark_gray'] = '1;30';
    $foreground_colors['blue'] = '0;34';
    $foreground_colors['light_blue'] = '1;34';
    $foreground_colors['green'] = '0;32';
    $foreground_colors['light_green'] = '1;32';
    $foreground_colors['cyan'] = '0;36';
    $foreground_colors['light_cyan'] = '1;36';
    $foreground_colors['red'] = '0;31';
    $foreground_colors['light_red'] = '1;31';
    $foreground_colors['purple'] = '0;35';
    $foreground_colors['light_purple'] = '1;35';
    $foreground_colors['brown'] = '0;33';
    $foreground_colors['yellow'] = '1;33';
    $foreground_colors['light_gray'] = '0;37';
    $foreground_colors['white'] = '1;37';

    $background_colors['black'] = '40';
    $background_colors['red'] = '41';
    $background_colors['green'] = '42';
    $background_colors['yellow'] = '43';
    $background_colors['blue'] = '44';
    $background_colors['magenta'] = '45';
    $background_colors['cyan'] = '46';
    $background_colors['light_gray'] = '47';

    $colored_string = "";

    // Check if given foreground color found
    if (isset($foreground_colors[$fColor])) {
        $colored_string .= "\033[" . $foreground_colors[$fColor] . "m";
    }
    // Check if given background color found
    if (isset($background_colors[$bColor])) {
        $colored_string .= "\033[" . $background_colors[$bColor] . "m";
    }

    // Add string and end coloring
    $colored_string .=  $string . "\033[0m";

    return $colored_string;
}
