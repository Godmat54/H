package bumutesystem;

import org.osgi.service.component.annotations.Component;
import org.osgi.service.component.annotations.Reference;

import com.equo.comm.api.ICommService;
import com.equo.comm.api.actions.IActionHandler;

@Component
public class MyEventHandler implements IActionHandler {

	@Reference
	private ICommService commService;

	public void listenToFront(String payload) {
		System.out.println("Payload from Front: " + payload);
	}

	public void sendToFront() {
		commService.send("listenToJava", "Hello from Java");
	}

	public String returnToFront() {
		return "Hello from Java";
	}
}
