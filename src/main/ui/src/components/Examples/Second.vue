<script setup>
import { EquoCommService } from "@equo/comm";
import { ref } from "vue";

const receivedMessage = ref("");

const receive = () => {
  EquoCommService.send("sendToFront");
  EquoCommService.on("listenToJava", () => {
    receivedMessage.value = "Hi, I am a message from the backend!";
  });
  setTimeout(() => {
    receivedMessage.value = "";
  }, 2000);
};
</script>

<template>
  <div>
    <h5>Send a message from Java to Javascript</h5>
    <div class="codes">
      <img width="30" src="../../assets/js.png" />
      <div>
        <pre><code>EquoCommService.on(&quot;listenToJava&quot;, () =&gt; {
  // Actions
});</code></pre>
      </div>
      <img width="30" src="../../assets/java.png" />
      <div>
        <pre><code>@Component
public class MyEventHandler implements IActionHandler {

	@Reference
	private ICommService commService;

	public void sendToFront() {
		commService.send(&quot;listenToJava&quot;);
	}
}
</code></pre>
      </div>
    </div>
    <div class="try-it">
      <button @click="receive">Try it!</button>
      <div />
      <p>{{ receivedMessage }}</p>
    </div>
  </div>
</template>
