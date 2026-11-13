package com.scubatana.app;

import android.app.Activity;
import android.app.PendingIntent;
import android.content.Intent;
import android.content.IntentFilter;
import android.net.Uri;
import android.nfc.NdefMessage;
import android.nfc.NdefRecord;
import android.nfc.NfcAdapter;
import android.os.Build;
import android.os.Parcelable;
import android.os.PatternMatcher;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

@CapacitorPlugin(name = "Nfc")
public class NfcPlugin extends Plugin {

	private static final String TAG_HOST = "scuba.picsou06.fr";
	private static final String TAG_PATH_PREFIX = "/b/";

	// Survit à la recréation de l'activité (rotation) : getIntent() rend alors encore l'intent du scan de départ.
	private static String lastDeliveredUrl;

	private NfcAdapter nfcAdapter;
	private String pendingUrl;

	@Override
	public void load() {
		nfcAdapter = NfcAdapter.getDefaultAdapter(getContext());
		Activity activity = getActivity();
		Intent intent = activity == null ? null : activity.getIntent();
		String initialUrl = extractUrl(intent);
		boolean fromRecents = intent != null && (intent.getFlags() & Intent.FLAG_ACTIVITY_LAUNCHED_FROM_HISTORY) != 0;

		if (initialUrl != null && !fromRecents && !initialUrl.equals(lastDeliveredUrl)) {
			pendingUrl = initialUrl;
			lastDeliveredUrl = initialUrl;
		}
	}

	@PluginMethod
	public void isAvailable(PluginCall call) {
		JSObject result = new JSObject();
		boolean available = nfcAdapter != null;
		boolean enabled = available && nfcAdapter.isEnabled();
		result.put("available", available);
		result.put("enabled", enabled);
		call.resolve(result);
	}

	@PluginMethod
	public void takePending(PluginCall call) {
		JSObject result = new JSObject();
		result.put("url", pendingUrl);
		pendingUrl = null;
		call.resolve(result);
	}

	@Override
	protected void handleOnNewIntent(Intent intent) {
		super.handleOnNewIntent(intent);
		String url = extractUrl(intent);

		if (url == null) {
			return;
		}

		lastDeliveredUrl = url;

		if (hasListeners("tag")) {
			JSObject data = new JSObject();
			data.put("url", url);
			notifyListeners("tag", data, false);
		} else {
			pendingUrl = url;
		}
	}

	@Override
	protected void handleOnResume() {
		super.handleOnResume();
		Activity activity = getActivity();

		if (activity == null || activity.isFinishing() || nfcAdapter == null || !nfcAdapter.isEnabled()) {
			return;
		}

		try {
			Intent intent = new Intent(activity, activity.getClass()).addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP);
			int flags = PendingIntent.FLAG_UPDATE_CURRENT
				| (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S ? PendingIntent.FLAG_MUTABLE : 0);
			PendingIntent pendingIntent = PendingIntent.getActivity(activity, 0, intent, flags);

			IntentFilter ndefFilter = new IntentFilter(NfcAdapter.ACTION_NDEF_DISCOVERED);
			ndefFilter.addDataScheme("https");
			ndefFilter.addDataAuthority(TAG_HOST, null);
			ndefFilter.addDataPath(TAG_PATH_PREFIX, PatternMatcher.PATTERN_PREFIX);

			nfcAdapter.enableForegroundDispatch(activity, pendingIntent, new IntentFilter[] { ndefFilter }, null);
		} catch (Exception ignored) {
			// Ignore foreground dispatch failures on restricted or non-NFC environments.
		}
	}

	@Override
	protected void handleOnPause() {
		Activity activity = getActivity();

		if (activity != null && nfcAdapter != null) {
			try {
				nfcAdapter.disableForegroundDispatch(activity);
			} catch (Exception ignored) {
				// Ignore disable failures when activity is backgrounding cleanly.
			}
		}

		super.handleOnPause();
	}

	private String extractUrl(Intent intent) {
		if (intent == null || !NfcAdapter.ACTION_NDEF_DISCOVERED.equals(intent.getAction())) {
			return null;
		}

		String dataString = intent.getDataString();

		if (dataString != null && isBorneUri(Uri.parse(dataString))) {
			return dataString;
		}

		Parcelable[] rawMessages = readNdefMessages(intent);

		if (rawMessages == null) {
			return null;
		}

		for (Parcelable raw : rawMessages) {
			if (!(raw instanceof NdefMessage)) {
				continue;
			}

			for (NdefRecord record : ((NdefMessage) raw).getRecords()) {
				Uri uri = record.toUri();

				if (isBorneUri(uri)) {
					return uri.toString();
				}
			}
		}

		return null;
	}

	@SuppressWarnings("deprecation")
	private Parcelable[] readNdefMessages(Intent intent) {
		return intent.getParcelableArrayExtra(NfcAdapter.EXTRA_NDEF_MESSAGES);
	}

	private boolean isBorneUri(Uri uri) {
		if (uri == null) {
			return false;
		}

		String scheme = uri.getScheme();
		String path = uri.getPath();

		return "https".equalsIgnoreCase(scheme)
			&& TAG_HOST.equalsIgnoreCase(uri.getHost())
			&& path != null
			&& path.startsWith(TAG_PATH_PREFIX);
	}
}
