import MwWindow from '@/@types/mediawiki/MwWindow';
import BridgeDomElementsSelector from '@/mediawiki/BridgeDomElementsSelector';
import { SelectedElement } from '@/mediawiki/SelectedElement';
import Dispatcher from '@/mediawiki/Dispatcher';

const APP_MODULE = 'wikibase.client.data-bridge.app';
const WBREPO_MODULE = 'mw.config.values.wbRepo';
const FOREIGNAPI_MODULE = 'mediawiki.ForeignApi';
const ULS_MODULE = 'jquery.uls.data';
const MWLANGUAGE_MODULE = 'mediawiki.language';

function stopNativeClickHandling( event: Event ): void {
	event.preventDefault();
	event.stopPropagation();
}

export default async (): Promise<void> => {
	const mwWindow = window as MwWindow,
		dataBridgeConfig = mwWindow.mw.config.get( 'wbDataBridgeConfig' );
	if ( dataBridgeConfig.hrefRegExp === null ) {
		mwWindow.mw.log.warn(
			'data bridge config incomplete: dataBridgeHrefRegExp missing',
		);
		return;
	}
	const bridgeElementSelector = new BridgeDomElementsSelector( dataBridgeConfig.hrefRegExp );
	const linksToOverload: SelectedElement[] = bridgeElementSelector.selectElementsToOverload();
	if ( linksToOverload.length > 0 ) {
		const dispatcherPromise = mwWindow.mw.loader.using( [
			APP_MODULE,
			WBREPO_MODULE,
			FOREIGNAPI_MODULE,
			ULS_MODULE,
			MWLANGUAGE_MODULE,
		] ).then( ( require ) => {
			const app = require( APP_MODULE );
			return new Dispatcher( mwWindow, app, dataBridgeConfig );
		} );

		linksToOverload.forEach( ( selectedElement: SelectedElement ) => {
			let isOpening = false;
			selectedElement.link.setAttribute( 'aria-haspopup', 'dialog' );
			selectedElement.link.addEventListener( 'click', async ( event: MouseEvent ) => {
				if ( event.altKey || event.ctrlKey || event.shiftKey || event.metaKey ) {
					return;
				}

				stopNativeClickHandling( event );
				if ( isOpening ) {
					return; // user clicked link again while we were awaiting dispatcherPromise, ignore
				}
				isOpening = true;
				const dispatcher = await dispatcherPromise;
				dispatcher.dispatch( selectedElement );
				isOpening = false;
			} );
		} );

		await dispatcherPromise; // tests need to know when they can expect the click listeners to work
	}
};
