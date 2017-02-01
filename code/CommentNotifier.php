<?php

/**
 * Extension applied to CommentingController to invoke notifications
 *
 * Relies on the parent object to {@see Comment} having the {@see CommentNotifiable} extension applied
 */
class CommentNotifier extends Extension {

	/**
	 * Notify Members of the post there is a new comment.
	 *
	 * @param Comment $comment
	 */
	public function onAfterPostComment(Comment $comment) {
        $blogPost = $comment->getParent();
		if(!$blogPost || !$blogPost->Parent()) return;

        // assumption 1; ParentID is the same SiteTree object of the Blog Post page since this component (Comments)
        // is currently only used on the Blog Post page itself. Retrieving the Blog Moderator is therefor rather easy
        // and doesn't need to use the 'updateNotificationRecipients' extension points for now.
        // assumption 2; a BlogPost is a child of a Blog
		$blog = $blogPost->Parent();

        // send a notification to the blog moderators
        foreach ($blog->Moderators() as $moderator) {
            $this->notifyRecipient($comment, $blogPost, $moderator);
        }

		// send a notification to the comment's author
        $this->notifyRecipient($comment, $blogPost, $comment->Email);

        die();
	}

	/**
	 * Validates for RFC 2822 compliant email adresses.
	 *
	 * @see http://www.regular-expressions.info/email.html
	 * @see http://www.ietf.org/rfc/rfc2822.txt
	 *
	 * @param string $email
	 * @return boolean
	 */
	public function isValidEmail($email) {
		if(!$email) return false;

		$pcrePattern = '^[a-z0-9!#$%&\'*+/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&\'*+/=?^_`{|}~-]+)*'
			. '@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$';

		// PHP uses forward slash (/) to delimit start/end of pattern, so it must be escaped
		$pregSafePattern = str_replace('/', '\\/', $pcrePattern);

		return preg_match('/' . $pregSafePattern . '/i', $email);
	}

    /**
     * Send comment notification to a given recipient
     *
     * @param Comment $comment
     * @param DataObject $blogPost Object with the {@see CommentNotifiable} extension applied
     * @param Member|string $recipient Either a member object or an email address to which notifications should be sent
     * @return bool status of the email being send
     */
    private function notifyRecipient($comment, $blogPost, $recipient) {
        $subject = $blogPost->notificationSubject($comment, $recipient);
        $sender = $blogPost->notificationSender($comment, $recipient);
        $template = $blogPost->notificationTemplate($comment, $recipient);
        $this->notifyRecipient($comment, $blogPost, $recipient, $subject, $sender, $template);
    }

	/**
	 * Send comment notification to a given recipient
	 *
	 * @param Comment $comment
	 * @param DataObject $parent Object with the {@see CommentNotifiable} extension applied
	 * @param Member|string $recipient Either a member object or an email address to which notifications should be sent
     * @param string $subject the subject line of the email message
     * @param string $sender the sender of the email message
     * @param string $template the content of the email message
     * @return bool status of the email being send
	 */
	private function sendEmail($comment, $parent, $recipient, $subject, $sender, $template) {

		// Validate email
		// Important in case of the owner being a default-admin or a username with no contact email
		$to = $recipient instanceof Member
			? $recipient->Email
			: $recipient;
		if(!$this->isValidEmail($to)) return;

		// Prepare the email
		$email = new Email();
		$email->setSubject($subject);
		$email->setFrom($sender);
		$email->setTo($to);
		$email->setTemplate($template);
		$email->populateTemplate(array(
			'Parent' => $parent,
			'Comment' => $comment,
			'Recipient' => $recipient
		));
		if($recipient instanceof Member) {
			$email->populateTemplate(array(
				'ApproveLink' => $comment->ApproveLink($recipient),
				'HamLink' => $comment->HamLink($recipient),
				'SpamLink' => $comment->SpamLink($recipient),
				'DeleteLink' => $comment->DeleteLink($recipient),
			));
		}

		// Until invokeWithExtensions supports multiple arguments
		if(method_exists($this->owner, 'updateCommentNotification')) {
			$this->owner->updateCommentNotification($email, $comment, $recipient);
		}
		$this->owner->extend('updateCommentNotification', $email, $comment, $recipient);

		var_dump($email);
		//return $email->send();
        return true;
	}
}
