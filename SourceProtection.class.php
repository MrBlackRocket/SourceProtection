<?php
/**
 * sourceProtection
 *
 *
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 *
 */

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\EditPage\EditPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\User;


class SourceProtection {
	/** @var string[] */
	private const BLOCKED_ACTIONS = [
		'delete',
		'edit',
		'history',
		'info',
		'markpatrolled',
		'move',
		'raw',
		'revert',
		'revisiondelete',
		'rollback',
	];

	/**
	 * Dive into the skin. Check if a user may edit. If not, remove tabs.
	 * @param SkinTemplate $sktemplate
	 * @param array &$links
	 */
	public static function hideSource( SkinTemplate $sktemplate, array &$links ): void {
		unset( $links['views']['viewsource'] );

		$title = $sktemplate->getTitle();
		if ( !$title ) {
			return;
		}

		if ( self::userMayEdit( $title, $sktemplate->getUser() ) ) {
			return;
		}

		unset( $links['views']['form_edit'] );
		unset( $links['views']['history'] );
	}

	/**
	 * If a user has no edit rights, then make sure it is hard for them to view the source of a document
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param array|string &$result
	 */
	public static function disableActions( $title, $user, $action, &$result ) {
		if ( !self::isBlockedAction( $action ) || self::userMayEdit( $title, $user ) ) {
			return true;
		}

		$result = [ 'badaccess-group0' ];
		return false;
	}

	/**
	 * Intercept direct action and diff requests before MediaWiki renders them.
	 *
	 * @param OutputPage $output
	 * @param Article $article
	 * @param Title $title
	 * @param User $user
	 * @param WebRequest $request
	 * @param ActionEntryPoint $mediaWiki
	 * @return bool
	 */
	public static function preventActionAccess(
		OutputPage $output,
		$article,
		Title $title,
		User $user,
		WebRequest $request,
		ActionEntryPoint $mediaWiki
	): bool {
		if ( self::userMayEdit( $title, $user ) ) {
			return true;
		}

		if ( !self::shouldBlockRequest( $request, $mediaWiki->getAction() ) ) {
			return true;
		}

		$output->redirect( $title->getLocalURL() );
		return false;
	}

	/**
	 * Prevent ShowReadOnly form to be shown. We should never get here anymore, but just in case.
	 * @param EditPage $editPage
	 * @param OutputPage $output
	 */
	public static function doNotShowReadOnlyForm( EditPage $editPage, OutputPage $output ): void {
		$title = $editPage->getTitle();
		if ( !self::userMayEdit( $title, $output->getUser() ) ) {
			$output->redirect( $editPage->getContextTitle() );
		}
	}

	public static function isBlockedAction( string $action ): bool {
		return in_array( $action, self::BLOCKED_ACTIONS, true );
	}

	public static function shouldBlockRequest( WebRequest $request, string $action ): bool {
		return $request->getVal( 'diff' ) !== null || self::isBlockedAction( $action );
	}

	private static function userMayEdit( Title $title, User $user ): bool {
		return MediaWikiServices::getInstance()
			->getPermissionManager()
			->userHasRight( $user, 'edit' );
	}

}
