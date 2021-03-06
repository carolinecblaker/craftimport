<?php

namespace Craft;

class CraftImportService extends BaseApplicationComponent
{
    public function __construct()
    {

    }

    public function loadEntries()
    {
        $retVal = true;

        // Use SimpleXML to fetch an XML export of channel data from an ExpressionEngine site
        $xml = simplexml_load_file('http://www.DOMAIN.com/blog/export');
        $importTags = true;
        // If importing tags, set your Tag Set ID
        $tagSetId = 1;
        // If importing tags, set your Tag Field ID
        $tagFieldId = 3;
        $sectionId = 3; // Visit settings for your Section and check the URL
        $typeId = 3; // Visit Entry Types for your Section and check the URL for the Entry Type


        foreach ($xml->blog[0]->entry as $importEntry) {
           // Validate fetch on screen
            /*echo $importEntry->entry_date . '<br />';
            echo $importEntry->title . '<br />';
            echo $importEntry->slug . '<br />';
            echo $importEntry->post . '<br />';
            echo '<br />';*/

            // Swap Assets URLs in posts
            // http://s3.amazonaws.com/YOURBUCKET/uploads
            // $newUrl = addslashes('http://s3.amazonaws.com/YOURBUCKET/uploads'); // Actually...Should not be needed
            $newUrl = 'http://s3.amazonaws.com/YOURBUCKET/uploads';
            // Make sure you reference this variable below!
            $post = $importEntry->post;
            // Make sure you run the string containing a subsequent substring first!
            $post = str_replace('http://www.DOMAIN.com/images/uploads', $newUrl, $post);
            $post = str_replace('/images/uploads', $newUrl, $post);

            // Check for existing entry
            $command = craft()->db->createCommand();
            $entryRecord =    $command
                        ->select('elementId')
                        ->from('elements_i18n')
                        ->where(array("AND", "slug='" . $importEntry->slug . "'"))
                        ->queryRow();

            // If existing entry, load that; Else new entry
            if (is_null($entryRecord['elementId']))
            {
                $entry = new EntryModel();
              // echo 'null';
            }
            else
            {
                $entry = craft()->entries->getEntryById( $entryRecord['elementId'] );
                //echo $entryRecord['elementId'];
            }
            //echo "\n\n";

             // Find these in craft/app/models/EntryModel
            $entry->sectionId = $sectionId;
            $entry->typeId = $typeId; 
            $entry->authorId = 1; // 1 for Admin
            $entry->enabled = true;
            $entry->postDate = $importEntry->entry_date;
            $entry->slug = $importEntry->slug;
            $entry->getContent()->setAttributes(array(
                'title' => $importEntry->title,
                'post' => $post
            ));
           
            if ( craft()->entries->saveEntry($entry) )
            {
                // Note that we're doing nothing to limit the number of records processed
             //   echo "Entry saved<br />\n\n";
                if ( $importTags && (count($importEntry->categories->category) > 0) ) {
                // print_r($importTags); echo "<br />\n\n";
                    $command = craft()->db->createCommand();
                    $entryRecord =  $command
                                    ->select('elementId')
                                    ->from('elements_i18n')
                                    ->where("slug='" . $importEntry->slug . "'")
                                    ->queryRow();

                    $tags = array();

                    foreach ($importEntry->categories->category as $category) {
                    //echo "tag process <br>\n\n";
                    
                        $tag = new TagModel($tagSetId);
                     //   echo "TAG SET ID". $tagSetId;
                        
                        $tag->groupId = $tagSetId;
                      // echo "hee";
                        $tag->name = $category;
                     //   print_r($tag);
                        
                        craft()->tags->saveTag($tag);

                        $command = craft()->db->createCommand();
                        $tagRecord =  $command
                                        ->select('id')
                                        ->from('tags')
                                        ->where("name='" . $category . "'")
                                        ->queryRow();
                       // echo $tagRecord;
                      //  echo 'entry: ' . $entryRecord['elementId'] . "<br />";
                       // echo 'tag: ' . $tagRecord['id'] . "<br />";

                        $tags[] = $tagRecord['id'];
                    }
                    $tagFieldRecord = FieldRecord::model()->findByPk($tagFieldId);

					$tagFieldModel = FieldModel::populateModel($tagFieldRecord);
					
					$entryRecord = EntryRecord::model()->findById($entryRecord['elementId']);

					$entryModel = EntryModel::populateModel($entryRecord);
					
					//echo  $entryRecord['elementId']." ->the string<br />\n\n";
                    craft()->relations->saveRelations($tagFieldModel, $entryModel, $tags);
                    
                }
                continue;
            } else {
            
         
                $retVal = false;
                break;
            }
        }
        return $retVal;
    }
}
