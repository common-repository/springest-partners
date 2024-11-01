<?php
/**
* @package Springest for Wordpress
*/

class SpringestTraining {
    
    var $id;
    var $name;
    var $description;
    var $institute;
    var $price;
    var $time;
    var $currency;
    var $level;
    var $maxParticipants;
    var $reviews;
    var $averageRating;
    var $url;
    var $informationRequestUrl;
    
    function __construct($id, $properties = null) {
        if(!isset($properties)) {
            $api = new SpringestApi();
            $properties = $api->getTraining($id);
        }
        if($properties) {
            $this->id = $properties->id;
            $this->name = $properties->name;
            $this->description = $properties->description;
            $this->excerpt = $this->truncate(strip_tags($properties->description), 300, "...", false);
            $this->institute = new SpringestInstitute($properties->institute_id, $properties->institute);
            $this->price = $properties->price;
            $this->time = ($properties->total_course_days) ? sprintf(_n(__('1 day', 'springest'), __('%s days', 'springest'), $properties->total_course_days), $properties->total_course_days) : null;
            $this->currency = $properties->price_currency;
            $this->level = (isset($properties->levels)) ? $properties->levels[0]->name : null;
            $this->maxParticipants = (isset($properties->max_participants)) ? $properties->max_participants : null;
            $this->reviews = $properties->reviews;
            $this->averageRating = ($properties->reviews) ? round($properties->average_rating,1) : 0;
            $this->url = ($properties->url) ? $properties->url : '';
            $this->informationRequestUrl = ($properties->information_request_url) ? $properties->information_request_url : '';
        }
    }
    
    function createPage() {
        $output = '<div class="springest-training">';
        $output .= '<img src="'.$this->institute->logo.'" alt="'.$this->institute->name.'" width="190" height="100" class="springest-training-image">';
        $output .= '<table cellspacing="0" class="springest-training-properties">';
        $output .= '<tr>';
        $output .= '<th scope="col" width="40%">'.__('Institute', 'springest').':</th>';
        $output .= '<td width="60%">'.$this->institute->name.'</td>';
        $output .= '</tr>';
        $output .= '<tr>';
        $output .= '<th scope="col">'.__('Price', 'springest').':</th>';
        $output .= '<td>'.$this->currency." ".$this->price.",-".'</td>';
        $output .= '</tr>';
        if($this->level) {
            $output .= '<tr>';
            $output .= '<th scope="col">'.__('Level', 'springest').':</th>';
            $output .= '<td>'.$this->level.'</td>';
            $output .= '</tr>';
        }
        if($this->maxParticipants) {
            $output .= '<tr>';
            $output .= '<th scope="col">'.__('Max. participants', 'springest').':</th>';
            $output .= '<td>'.$this->maxParticipants.'</td>';
            $output .= '</tr>';
        }
        if($this->time) {
            $output .= '<tr>';
            $output .= '<th scope="col">'.__('Total time', 'springest').':</th>';
            $output .= '<td>'.$this->time.'</td>';
            $output .= '</tr>';
        }
        if($this->reviews) {
            $output .= '<tr>';
            $output .= '<th scope="col">'.__('Rating', 'springest').':</th>';
            $output .= '<td>'.$this->averageRating.' ('.sprintf(_n(__('1 review', 'springest'), __('%d reviews', 'springest'), $this->reviews, 'springest'),$this->reviews).')</td>';
            $output .= '</tr>';
        }
        
        $output .= '</table></div><br/>';
        $output .= '<div class="springest-training-content">';
        $infoLinks = array();
        if($this->url) {
            $infoLinks[] = '<a title="'.$this->name.'" href="'.$this->url.'">'.__('More information', 'springest').'</a>';
        }
        if($this->informationRequestUrl) {
            $infoLinks[] = '<div class="springest-button"><button onclick="document.location.href=\''.$this->informationRequestUrl.'\';">'.__('Request information', 'springest').'</button></div>';
        }
        $output .= '<div class="springest-training-actions springest-actions-top">'.implode(" ", $infoLinks).'</div>';
        $output .= '<div class="springest-training-description">'.$this->description.'</div>';
        $output .= '<div class="springest-training-actions springest-actions-bottom">'.implode(" ", $infoLinks).'</div>';
        $output .= '</div>';
        return $output;
    }
    
    function truncate($text, $length = 100, $ending = '...', $exact = true, $considerHtml = false) {
    	if ($considerHtml) {
    		// if the plain text is shorter than the maximum length, return the whole text
    		if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
    			return $text;
    		}
    		// splits all html-tags to scanable lines
    		preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
    		$total_length = strlen($ending);
    		$open_tags = array();
    		$truncate = '';
    		foreach ($lines as $line_matchings) {
    			// if there is any html-tag in this line, handle it and add it (uncounted) to the output
    			if (!empty($line_matchings[1])) {
    				// if it's an "empty element" with or without xhtml-conform closing slash (f.e. <br/>)
    				if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
    					// do nothing
    				// if tag is a closing tag (f.e. </b>)
    				} else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
    					// delete tag from $open_tags list
    					$pos = array_search($tag_matchings[1], $open_tags);
    					if ($pos !== false) {
    						unset($open_tags[$pos]);
    					}
    				// if tag is an opening tag (f.e. <b>)
    				} else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
    					// add tag to the beginning of $open_tags list
    					array_unshift($open_tags, strtolower($tag_matchings[1]));
    				}
    				// add html-tag to $truncate'd text
    				$truncate .= $line_matchings[1];
    			}
    			// calculate the length of the plain text part of the line; handle entities as one character
    			$content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
    			if ($total_length+$content_length> $length) {
    				// the number of characters which are left
    				$left = $length - $total_length;
    				$entities_length = 0;
    				// search for html entities
    				if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
    					// calculate the real length of all entities in the legal range
    					foreach ($entities[0] as $entity) {
    						if ($entity[1]+1-$entities_length <= $left) {
    							$left--;
    							$entities_length += strlen($entity[0]);
    						} else {
    							// no more characters left
    							break;
    						}
    					}
    				}
    				$truncate .= substr($line_matchings[2], 0, $left+$entities_length);
    				// maximum lenght is reached, so get off the loop
    				break;
    			} else {
    				$truncate .= $line_matchings[2];
    				$total_length += $content_length;
    			}
    			// if the maximum length is reached, get off the loop
    			if($total_length>= $length) {
    				break;
    			}
    		}
    	} else {
    		if (strlen($text) <= $length) {
    			return $text;
    		} else {
    			$truncate = substr($text, 0, $length - strlen($ending));
    		}
    	}
    	// if the words shouldn't be cut in the middle...
    	if (!$exact) {
    		// ...search the last occurance of a space...
    		$spacepos = strrpos($truncate, ' ');
    		if (isset($spacepos)) {
    			// ...and cut the text in this position
    			$truncate = substr($truncate, 0, $spacepos);
    		}
    	}
    	// add the defined ending to the text
    	$truncate .= $ending;
    	if($considerHtml) {
    		// close all unclosed html-tags
    		foreach ($open_tags as $tag) {
    			$truncate .= '</' . $tag . '>';
    		}
    	}
    	return $truncate;
    }

}
?>