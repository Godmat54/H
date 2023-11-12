<script setup>
import { EquoCommService } from "@equo/comm";

const send = () => {
  EquoCommService.send(
    "listenToFront",
    "Hi, I am a message from the frontend!"
  );
};
</script>

<template>
  <div>
    <h5>Send a message from Javascript to Java</h5>
    <div class="codes">
      <img width="30" src="../../assets/js.png" />
      <div>
        <pre><code>EquoCommService.send(
  &quot;listenToFront&quot;,
  &quot;Hi, I am a message from the frontend!&quot;
);</code></pre>
      </div>
      <img width="30" src="../../assets/java.png" />
      <div>
        <pre><code>@Component
public class MyEventHandler implements IActionHandler {

	@Reference
	private ICommService commService;

	public void listenToFront(String payload) {
		System.out.println("Payload from Front: " + payload);
	}
}
</code></pre>
      </div>
    </div>
    <div class="try-it">
      <button @click="send">Try it!</button>
      <div />
      <p>Check the Java console!</p>
    </div>
  </div>
</template>

<style scoped>

</style>
