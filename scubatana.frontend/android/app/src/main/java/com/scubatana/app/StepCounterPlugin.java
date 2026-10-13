package com.scubatana.app;

import android.Manifest;
import android.content.Context;
import android.content.Intent;
import android.hardware.Sensor;
import android.hardware.SensorEvent;
import android.hardware.SensorEventListener;
import android.hardware.SensorManager;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;

import com.getcapacitor.JSArray;
import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.google.android.gms.common.ConnectionResult;
import com.google.android.gms.common.GoogleApiAvailability;
import com.google.android.gms.fitness.FitnessLocal;
import com.google.android.gms.fitness.LocalRecordingClient;
import com.google.android.gms.fitness.data.LocalBucket;
import com.google.android.gms.fitness.data.LocalDataPoint;
import com.google.android.gms.fitness.data.LocalDataSet;
import com.google.android.gms.fitness.data.LocalDataType;
import com.google.android.gms.fitness.data.LocalField;
import com.google.android.gms.fitness.request.LocalDataReadRequest;

import java.util.concurrent.TimeUnit;

@CapacitorPlugin(
	name = "StepCounter",
	permissions = { @Permission(alias = "activity", strings = { Manifest.permission.ACTIVITY_RECOGNITION }) }
)
public class StepCounterPlugin extends Plugin implements SensorEventListener {

	private SensorManager sensorManager;
	private Sensor stepSensor;
	private boolean listening = false;

	@Override
	public void load() {
		sensorManager = (SensorManager) getContext().getSystemService(Context.SENSOR_SERVICE);
		stepSensor = sensorManager == null ? null : sensorManager.getDefaultSensor(Sensor.TYPE_STEP_COUNTER);
	}

	@PluginMethod
	public void isAvailable(PluginCall call) {
		JSObject result = new JSObject();
		result.put("available", stepSensor != null);
		call.resolve(result);
	}

	@PluginMethod
	public void start(PluginCall call) {
		if (stepSensor == null) {
			call.reject("Step sensor unavailable", "UNAVAILABLE");
			return;
		}

		if (!hasActivityPermission()) {
			call.reject("Activity recognition permission missing", "PERMISSION_DENIED");
			return;
		}

		if (!listening) {
			listening = sensorManager.registerListener(this, stepSensor, SensorManager.SENSOR_DELAY_UI);
		}

		if (!listening) {
			call.reject("Step sensor could not be started", "UNAVAILABLE");
			return;
		}

		call.resolve();
	}

	@PluginMethod
	public void stop(PluginCall call) {
		stopListening();
		call.resolve();
	}

	@PluginMethod
	public void startRecording(PluginCall call) {
		if (!recordingAvailable() || !hasActivityPermission()) {
			call.reject("Step recording unavailable", "UNAVAILABLE");
			return;
		}

		FitnessLocal.getLocalRecordingClient(getContext())
			.subscribe(LocalDataType.TYPE_STEP_COUNT_DELTA)
			.addOnSuccessListener(unused -> call.resolve())
			.addOnFailureListener(error -> call.reject("Step recording could not be started", "UNAVAILABLE", error));
	}

	@PluginMethod
	public void readRecorded(PluginCall call) {
		Long from = call.getLong("from");
		long until = System.currentTimeMillis();

		if (from == null || from >= until || !recordingAvailable() || !hasActivityPermission()) {
			call.reject("Recorded steps unavailable", "UNAVAILABLE");
			return;
		}

		LocalDataReadRequest request = new LocalDataReadRequest.Builder()
			.aggregate(LocalDataType.TYPE_STEP_COUNT_DELTA)
			.bucketByTime(1, TimeUnit.HOURS)
			.setTimeRange(from, until, TimeUnit.MILLISECONDS)
			.build();

		FitnessLocal.getLocalRecordingClient(getContext())
			.readData(request)
			.addOnSuccessListener(response -> {
				JSArray buckets = new JSArray();

				for (LocalBucket bucket : response.getBuckets()) {
					int steps = 0;

					for (LocalDataSet dataSet : bucket.getDataSets()) {
						for (LocalDataPoint point : dataSet.getDataPoints()) {
							steps += point.getValue(LocalField.FIELD_STEPS).asInt();
						}
					}

					if (steps > 0) {
						JSObject entry = new JSObject();
						entry.put("start", bucket.getStartTime(TimeUnit.MILLISECONDS));
						entry.put("steps", steps);
						buckets.put(entry);
					}
				}

				JSObject result = new JSObject();
				result.put("buckets", buckets);
				call.resolve(result);
			})
			.addOnFailureListener(error -> call.reject("Recorded steps could not be read", "UNAVAILABLE", error));
	}

	@PluginMethod
	public void openSettings(PluginCall call) {
		Intent intent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.fromParts("package", getContext().getPackageName(), null));
		intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
		getContext().startActivity(intent);
		call.resolve();
	}

	@Override
	public void onSensorChanged(SensorEvent event) {
		JSObject data = new JSObject();
		data.put("count", (long) event.values[0]);
		notifyListeners("steps", data, true);
	}

	@Override
	public void onAccuracyChanged(Sensor sensor, int accuracy) {
	}

	@Override
	protected void handleOnDestroy() {
		stopListening();
	}

	private boolean hasActivityPermission() {
		return Build.VERSION.SDK_INT < Build.VERSION_CODES.Q || getPermissionState("activity") == PermissionState.GRANTED;
	}

	private boolean recordingAvailable() {
		return GoogleApiAvailability.getInstance()
			.isGooglePlayServicesAvailable(getContext(), LocalRecordingClient.LOCAL_RECORDING_CLIENT_MIN_VERSION_CODE) == ConnectionResult.SUCCESS;
	}

	private void stopListening() {
		if (listening) {
			sensorManager.unregisterListener(this);
			listening = false;
		}
	}
}
