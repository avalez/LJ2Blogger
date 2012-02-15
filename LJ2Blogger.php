<?

set_include_path('xmlrpc-3.0.0.beta/lib');

include("xmlrpc.inc");

set_include_path('ZendGdata-1.11.9/library/');

/**
 * @see Zend_Loader
 */
require_once 'Zend/Loader.php';

/**
 * @see Zend_Date
 */
//Zend_Loader::loadClass('Zend_Date');

/**
 * @see Zend_Gdata
 */
Zend_Loader::loadClass('Zend_Gdata');

/**
 * @see Zend_Gdata_Query
 */
Zend_Loader::loadClass('Zend_Gdata_Query');

/**
 * @see Zend_Gdata_ClientLogin
 */
Zend_Loader::loadClass('Zend_Gdata_ClientLogin');

class LJSimpleCRUD
{
    public $blogID;

    public $login;

    public $password;

    public $client;

    /**
     * Constructor for the class. Takes in user credentials and generates the
     * the authenticated client object.
     *
     * @param  string $login    The user'
     * @param  string $password The user's password.
     * @return void
     */
    public function __construct($login, $password)
    {
		$this->login = $login;
		$this->password = $password;
		$this->client = new xmlrpc_client("/interface/xmlrpc", "www.livejournal.com", 80);
		/* (!!!) Все денные в ЖЖ хранятся в кодировке Unicode,
		используем и в нашем случае такую же кодировку */
		$client->request_charset_encoding = "UTF-8";
    }

    public function callrpc($name, $args) {
        $msg = new xmlrpcmsg('LJ.XMLRPC.'. $name,
            array(new xmlrpcval($args, "struct")));

		//echo nl2br($getcomments_msg->serialize());

		$r = $this->client->send($msg);

		if(!$r->faultCode())
		{
			/* сообщение принято успешно и вернулся XML-результат */
			$v = php_xmlrpc_decode($r->value());
			//print_r($v);
			return $v;
		}
		else
		{
			/* сервер вернул ошибку */
			print "An error occurred: ";
			print "Code: ".htmlspecialchars($r->faultCode());
			print "Reason: '".htmlspecialchars($r->faultString())."'\n";
		}	
    }

    public function getevents() {
		$client->request_charset_encoding = "UTF-8";

		$getevents_args = array(
				"username" => new xmlrpcval($this->login, "string"),
				"password" => new xmlrpcval($this->password, "string"),
				"selecttype" => new xmlrpcval("lastn", "string"),
				"ver" => new xmlrpcval(2, "int")
			);
	    $getevents_r = $this->callrpc("getevents", $getevents_args);
	    $events = $getevents_r["events"];

        $res = array();
	    foreach ($events as $event) {
		   if (ereg("([0-9]+).html$", $event["url"], $regs)) {
			$ditemid = $regs[1];
			$getcomments_args = array(
					"username" => new xmlrpcval($this->login, "string"),
					"password" => new xmlrpcval($this->password, "string"),
	                "journal"  => new xmlrpcval($this->login, "string"),
	                "ditemid"   => new xmlrpcval($ditemid, "string"),
					"ver" => new xmlrpcval(2, "int")
				);
		        $getcomments_r = $this->callrpc("getcomments", $getcomments_args);
		        $event["comments"] = $getcomments_r["comments"];
			
		    } 
	        $res[] = $event;
      	}

        return $res;
    }

}

/**
 * Class that contains all simple CRUD operations for Blogger.
 *
 * @category   Zend
 * @package    Zend_Gdata
 * @subpackage Demos
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class BSimpleCRUD
{
    /**
     * $blogID - Blog ID used for demo operations
     *
     * @var string
     */
    public $blogID;

    /**
     * $gdClient - Client class used to communicate with the Blogger service
     *
     * @var Zend_Gdata_Client
     */
    public $gdClient;


    /**
     * Constructor for the class. Takes in user credentials and generates the
     * the authenticated client object.
     *
     * @param  string $email    The user's email address.
     * @param  string $password The user's password.
     * @return void
     */
    public function __construct($email, $password)
    {
        $client = Zend_Gdata_ClientLogin::getHttpClient($email, $password, 'blogger');
        $this->gdClient = new Zend_Gdata($client);
    }

    /**
     * This function retrieves all the blogs associated with the authenticated
     * user and prompts the user to choose which to manipulate.
     *
     * Once the index is selected by the user, the corresponding blogID is
     * extracted and stored for easy access.
     *
     * @return void
     */
    public function promptForBlogID()
    {
        $query = new Zend_Gdata_Query('http://www.blogger.com/feeds/default/blogs');
        $feed = $this->gdClient->getFeed($query);
        $this->printFeed($feed);
        $input = getInput("\nSelection");

        //id text is of the form: tag:blogger.com,1999:user-blogID.blogs
        $idText = explode('-', $feed->entries[$input]->id->text);
        $this->blogID = $idText[2];
    }

    /**
     * This function creates a new Zend_Gdata_Entry representing a blog
     * post, and inserts it into the user's blog. It also checks for
     * whether the post should be added as a draft or as a published
     * post.
     *
     * @param  string  $title   The title of the blog post.
     * @param  string  $content The body of the post.
     * @param  boolean $isDraft Whether the post should be added as a draft or as a published post
     * @return string The newly created post's ID
     */
    public function createPost($title, $content, $date, $isDraft=False)
    {
        // We're using the magic factory method to create a Zend_Gdata_Entry.
        // http://framework.zend.com/manual/en/zend.gdata.html#zend.gdata.introdduction.magicfactory
        $entry = $this->gdClient->newEntry();

        $entry->title = $this->gdClient->newTitle(trim($title));
        $entry->content = $this->gdClient->newContent(trim($content));
        $entry->content->setType('text');
        $published = new Zend_Gdata_App_Extension_Published($date);
        $entry->setPublished($published);
        //echo $entry->getPublished()->getText() . "\n";
        $uri = "http://www.blogger.com/feeds/" . $this->blogID . "/posts/default";

        if ($isDraft)
        {
            $control = $this->gdClient->newControl();
            $draft = $this->gdClient->newDraft('yes');
            $control->setDraft($draft);
            $entry->control = $control;
        }

        $createdPost = $this->gdClient->insertEntry($entry, $uri);
        // format of id text: tag:blogger.com,1999:blog-blogID.post-postID
        $idText = explode('-', $createdPost->id->text);
        $postID = $idText[2];

        return $postID;
    }

    /**
     * Prints the titles of all the posts in the user's blog.
     *
     * @return void
     */
    public function printAllPosts()
    {
        $query = new Zend_Gdata_Query('http://www.blogger.com/feeds/' . $this->blogID . '/posts/default');
        $feed = $this->gdClient->getFeed($query);
        $this->printFeed($feed);
    }

    /**
     * Retrieves the specified post and updates the title and body. Also sets
     * the post's draft status.
     *
     * @param string  $postID         The ID of the post to update. PostID in <id> field:
     *                                tag:blogger.com,1999:blog-blogID.post-postID
     * @param string  $updatedTitle   The new title of the post.
     * @param string  $updatedContent The new body of the post.
     * @param boolean $isDraft        Whether the post will be published or saved as a draft.
     * @return Zend_Gdata_Entry The updated post.
     */
    public function updatePost($postID, $updatedTitle, $updatedContent, $isDraft)
    {
        $query = new Zend_Gdata_Query('http://www.blogger.com/feeds/' . $this->blogID . '/posts/default/' . $postID);
        $postToUpdate = $this->gdClient->getEntry($query);
        $postToUpdate->title->text = $this->gdClient->newTitle(trim($updatedTitle));
        $postToUpdate->content->text = $this->gdClient->newContent(trim($updatedContent));

        if ($isDraft) {
            $draft = $this->gdClient->newDraft('yes');
        } else {
            $draft = $this->gdClient->newDraft('no');
        }

        $control = $this->gdClient->newControl();
        $control->setDraft($draft);
        $postToUpdate->control = $control;
        $updatedPost = $postToUpdate->save();

        return $updatedPost;
    }

    /**
     * This function uses query parameters to retrieve and print all posts
     * within a specified date range.
     *
     * @param  string $startDate Beginning date, inclusive. Preferred format is a RFC-3339 date,
     *                           though other formats are accepted.
     * @param  string $endDate   End date, exclusive.
     * @return void
     */
    public function printPostsInDateRange($startDate, $endDate)
    {
        $query = new Zend_Gdata_Query('http://www.blogger.com/feeds/' . $this->blogID . '/posts/default');
        $query->setParam('published-min', $startDate);
        $query->setParam('published-max', $endDate);

        $feed = $this->gdClient->getFeed($query);
        $this->printFeed($feed);
    }

    /**
     * This function creates a new comment and adds it to the specified post.
     * A comment is created as a Zend_Gdata_Entry.
     *
     * @param  string $postID      The ID of the post to add the comment to. PostID
     *                             in the <id> field: tag:blogger.com,1999:blog-blogID.post-postID
     * @param  string $commentText The text of the comment to add.
     * @return string The ID of the newly created comment.
     */
    public function createComment($postID, $commentText, $date)
    {
        $uri = 'http://www.blogger.com/feeds/' . $this->blogID . '/' . $postID . '/comments/default';

        $newComment = $this->gdClient->newEntry();
        $newComment->content = $this->gdClient->newContent($commentText);
        $newComment->content->setType('text');
        $published = new Zend_Gdata_App_Extension_Published($date);
        $newComment->setPublished($published);
        $createdComment = $this->gdClient->insertEntry($newComment, $uri);

        echo 'Added new comment: ' . $createdComment->content->text . "\n";
        // Edit link follows format: /feeds/blogID/postID/comments/default/commentID
        $editLink = explode('/', $createdComment->getEditLink()->href);
        $commentID = $editLink[8];

        return $commentID;
    }

    /**
     * This function prints all comments associated with the specified post.
     *
     * @param  string $postID The ID of the post whose comments we'll print.
     * @return void
     */
    public function printAllComments($postID)
    {
        $query = new Zend_Gdata_Query('http://www.blogger.com/feeds/' . $this->blogID . '/' . $postID . '/comments/default');
        $feed = $this->gdClient->getFeed($query);
        $this->printFeed($feed);
    }

    /**
     * This function deletes the specified comment from a post.
     *
     * @param  string $postID    The ID of the post where the comment is. PostID in
     *                           the <id> field: tag:blogger.com,1999:blog-blogID.post-postID
     * @param  string $commentID The ID of the comment to delete. The commentID
     *                           in the editURL: /feeds/blogID/postID/comments/default/commentID
     * @return void
     */
    public function deleteComment($postID, $commentID)
    {
        $uri = 'http://www.blogger.com/feeds/' . $this->blogID . '/' . $postID . '/comments/default/' . $commentID;
        $this->gdClient->delete($uri);
    }

    /**
     * This function deletes the specified post.
     *
     * @param  string $postID The ID of the post to delete.
     * @return void
     */
    public function deletePost($postID)
    {
        $uri = 'http://www.blogger.com/feeds/' . $this->blogID . '/posts/default/' . $postID;
        $this->gdClient->delete($uri);
    }

    /**
     * Helper function to print out the titles of all supplied Blogger
     * feeds.
     *
     * @param  Zend_Gdata_Feed The feed to print.
     * @return void
     */
    public function printFeed($feed)
    {
        $i = 0;
        foreach($feed->entries as $entry)
        {
            echo "\t" . $i ." ". $entry->title->text . "\n";
            $i++;
        }
    }
}	

function getInput($text)
{
    echo $text.': ';
    return trim(fgets(STDIN));
}

date_default_timezone_set('UTC');

$lj = new LJSimpleCRUD("<email>", "<passsword>");
$blogger = new BSimpleCRUD("<email>", "<passsword>");

$blogger->promptForBlogID();
$events = $lj->getevents();
foreach ($events as $event) {
	$date = date(DATE_ATOM, strtotime($event["eventtime"]));
	$postid = $blogger->createPost($event["subject"], $event["event"], $date);
	$comments = $event["comments"];
	foreach ($comments as $comment) {
		$date = date(DATE_ATOM, strtotime($comment["datepost"]));
		$body = "LJ user ";
		if ($comment["identity_display"]) {
			$body .= $comment["identity_display"];
		} else {
		    $body .= $comment["postername"];
		}
		$body .= " wrote: <br/>" . $comment["body"];
		//echo $body;
		$blogger->createComment($postid, $body, $date);
	}
}
?>
