package com.scubatana.app;

import android.Manifest;
import android.location.Location;
import android.os.Build;

import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.google.android.gms.location.LocationServices;
import com.google.android.gms.location.Priority;
import com.google.android.gms.tasks.CancellationTokenSource;

@CapacitorPlugin(
	name = "Position",
	permissions = { @Permission(alias = "location", strings = { Manifest.permission.ACCESS_COARSE_LOCATION, Manifest.permission.ACCESS_FINE_LOCATION }) }
)
public class PositionPlugin extends Plugin {

	@PluginMethod
	public void current(PluginCall call) {
		if (getPermissionState("location") != PermissionState.GRANTED) {
			call.reject("Location permission missing", "PERMISSION_DENIED");
			return;
		}

		LocationServices.getFusedLocationProviderClient(getContext())
			.getCurrentLocation(Priority.PRIORITY_HIGH_ACCURACY, new CancellationTokenSource().getToken())
			.addOnSuccessListener(location -> {
				if (location == null) {
					call.reject("Location unavailable", "UNAVAILABLE");
					return;
				}

				call.resolve(toResult(location));
			})
			.addOnFailureListener(error -> call.reject("Location unavailable", "UNAVAILABLE", error));
	}

	private JSObject toResult(Location location) {
		JSObject result = new JSObject();
		result.put("latitude", location.getLatitude());
		result.put("longitude", location.getLongitude());
		result.put("accuracy", location.getAccuracy());
		result.put("mocked", isMocked(location));
		result.put("time", location.getTime());
		return result;
	}

	@SuppressWarnings("deprecation")
	private boolean isMocked(Location location) {
		return Build.VERSION.SDK_INT >= Build.VERSION_CODES.S ? location.isMock() : location.isFromMockProvider();
	}
}
